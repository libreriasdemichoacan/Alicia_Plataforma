<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';
require_once app_path('includes/branches.php');

function remote_customer_statement(string $customerCode, ?int $branchId = null): array
{
    $customerCode = trim($customerCode);
    if ($customerCode === '') {
        return ['enabled' => false, 'error' => 'El cliente no tiene número interno configurado.', 'customer' => null, 'movements' => []];
    }

    $branch = report_branch($branchId);
    if (!$branch && !app_config()['db']['remote_reports']['enabled']) {
        return ['enabled' => false, 'error' => null, 'customer' => null, 'movements' => []];
    }

    try {
        $pdo = $branch ? branch_pdo($branch) : remote_reports_db();
        $from = date('Y-01-01');
        $to = date('Y-m-d');

        $stmtCliente = $pdo->prepare('SELECT * FROM cliente WHERE c_cod = :cliente LIMIT 1');
        $stmtCliente->execute([':cliente' => $customerCode]);
        $customer = $stmtCliente->fetch();
        if (!$customer) {
            return ['enabled' => true, 'error' => 'No se encontró el cliente en la base remota.', 'customer' => null, 'movements' => []];
        }

        $sql = "
            SELECT id, '2' AS tipo, idtipo AS obs, fecha, fecha AS fechav, 0 AS cargos, monto AS abonos, 0 AS factura
            FROM devolucion
            WHERE cliente = :cliente_devolucion AND fecha >= :from_devolucion AND fecha <= :to_devolucion

            UNION ALL

            SELECT recibo AS id, '3' AS tipo, obs, fecha, fecha AS fechav, 0 AS cargos, monto AS abonos, 0 AS factura
            FROM pagocxc
            WHERE cliente = :cliente_pago AND fecha >= :from_pago AND fecha <= :to_pago

            UNION ALL

            SELECT id, '1' AS tipo, memo AS obs, fecha, vence AS fechav, total AS cargos, IF(pagado = 1, total, 0) AS abonos, factura
            FROM venta
            WHERE ccliente = :cliente_venta AND fecha >= :from_venta AND fecha <= :to_venta

            ORDER BY fecha DESC
            LIMIT 1000
        ";
        $stmtMovs = $pdo->prepare($sql);
        $stmtMovs->execute([
            ':cliente_devolucion' => $customerCode,
            ':from_devolucion' => $from,
            ':to_devolucion' => $to,
            ':cliente_pago' => $customerCode,
            ':from_pago' => $from,
            ':to_pago' => $to,
            ':cliente_venta' => $customerCode,
            ':from_venta' => $from,
            ':to_venta' => $to,
        ]);
        $movements = build_statement_balances($stmtMovs->fetchAll(), (float)($customer['saldo1'] ?? 0));

        return [
            'enabled' => true,
            'error' => null,
            'customer' => $customer,
            'movements' => $movements,
            'from' => $from,
            'to' => $to,
            'starting_balance' => (float)($customer['saldo1'] ?? 0),
        ];
    } catch (Throwable $exception) {
        error_log('Error al consultar estado de cuenta remoto: ' . $exception->getMessage());
        return ['enabled' => true, 'error' => 'No fue posible consultar el estado de cuenta remoto en este momento.', 'customer' => null, 'movements' => []];
    }
}

function build_statement_balances(array $rows, float $startingBalance): array
{
    $balance = $startingBalance;
    foreach ($rows as &$row) {
        $type = (int)$row['tipo'];
        $invoice = (int)$row['factura'];
        $charge = (float)$row['cargos'];
        $payment = (float)$row['abonos'];
        $row['type_label'] = match ($type) {
            1 => 'Venta',
            2 => 'Devolución',
            3 => 'Pago',
            default => 'Movimiento',
        };
        $row['detail_page'] = match ($type) {
            1 => 'remision',
            2 => 'devolucion',
            3 => 'pago',
            default => 'movimiento',
        };
        $row['due_date'] = $type === 1 ? $row['fechav'] : '-';
        $prefix = '';
        if ($type === 1) {
            $prefix = match ($invoice) {
                0 => 'T - ',
                1 => 'F - ',
                2 => 'R - ',
                default => '',
            };
        }
        $row['document_label'] = $prefix . $row['id'];
        $balance = $balance - $charge + $payment;
        $row['render_balance'] = $balance - $payment + $charge;
    }
    unset($row);
    return $rows;
}

function remote_sale_detail(string $customerCode, string $document, ?int $branchId = null): array
{
    $customerCode = trim($customerCode);
    $document = trim($document);
    if ($customerCode === '' || $document === '') {
        return ['error' => 'Datos incompletos para consultar el documento.', 'sale' => null, 'customer' => null, 'items' => []];
    }
    $branch = report_branch($branchId);
    if (!$branch && !app_config()['db']['remote_reports']['enabled']) {
        return ['error' => 'La consulta remota no está habilitada.', 'sale' => null, 'customer' => null, 'items' => []];
    }

    try {
        $pdo = $branch ? branch_pdo($branch) : remote_reports_db();
        $stmtVenta = $pdo->prepare('SELECT * FROM venta WHERE ccliente = :cliente AND (doc = :doc_value OR id = :id_value) LIMIT 1');
        $stmtVenta->execute([':cliente' => $customerCode, ':doc_value' => $document, ':id_value' => $document]);
        $sale = $stmtVenta->fetch();
        if (!$sale) {
            return ['error' => 'No se encontró el documento de venta solicitado.', 'sale' => null, 'customer' => null, 'items' => []];
        }

        $stmtCliente = $pdo->prepare('SELECT * FROM cliente WHERE c_cod = :cliente LIMIT 1');
        $stmtCliente->execute([':cliente' => $customerCode]);
        $customer = $stmtCliente->fetch();
        if (!$customer) {
            return ['error' => 'No se encontró el cliente en la base remota.', 'sale' => $sale, 'customer' => null, 'items' => []];
        }

        $stmtKardex = $pdo->prepare('SELECT k.*, l.titulo, l.autor, l.codbar
            FROM kardex k
            INNER JOIN libro l ON k.libro = l.id
            WHERE k.cliente = :cliente
              AND (k.idtipo = :doc_venta OR k.idtipo = :id_venta OR k.idtipo = :requested_document)
              AND k.tipo = "0"
            LIMIT 100');
        $stmtKardex->execute([
            ':cliente' => $customerCode,
            ':doc_venta' => $sale['doc'],
            ':id_venta' => $sale['id'],
            ':requested_document' => $document,
        ]);
        $items = build_sale_item_totals($stmtKardex->fetchAll());

        return ['error' => null, 'sale' => $sale, 'customer' => $customer, 'items' => $items['items'], 'totals' => $items['totals']];
    } catch (Throwable $exception) {
        error_log('Error al consultar detalle de venta remoto: ' . $exception->getMessage());
        return ['error' => 'No fue posible consultar el detalle del documento en este momento.', 'sale' => null, 'customer' => null, 'items' => []];
    }
}

function build_sale_item_totals(array $items): array
{
    $totals = ['subtotal' => 0.0, 'discount' => 0.0, 'tax' => 0.0, 'total' => 0.0];
    foreach ($items as &$item) {
        $price = (float)$item['precio'];
        $quantity = (float)$item['cantidad'];
        if ((int)$item['tipo'] === 5) {
            $quantity *= -1;
        }
        $discountRate = (float)$item['descuento'];
        $taxRate = (float)$item['impuesto'];
        $lineSubtotal = $quantity * $price;
        $lineDiscount = $lineSubtotal - ($lineSubtotal * (1 - $discountRate / 100));
        $lineTax = $lineSubtotal * (1 - $discountRate / 100) * ($taxRate / 100);
        $lineTotal = $lineSubtotal * (1 - $discountRate / 100) * (1 + $taxRate / 100);
        $item['quantity_render'] = $quantity;
        $item['line_total'] = $lineTotal;
        $totals['subtotal'] += $lineSubtotal;
        $totals['discount'] += $lineDiscount;
        $totals['tax'] += $lineTax;
        $totals['total'] += $lineTotal;
    }
    unset($item);
    return ['items' => $items, 'totals' => $totals];
}
