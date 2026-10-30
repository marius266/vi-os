<?php
function viReadPartsCart(): array {
    $json = $_POST['parts_cart_json'] ?? null;
    if (!is_string($json) || strlen($json) > 100000) { http_response_code(400); exit('Invalid parts cart'); }
    $items = json_decode($json, true);
    if (!is_array($items) || count($items) > 150) { http_response_code(400); exit('Invalid parts list'); }
    $clean = []; $cents = 0;
    foreach ($items as $item) {
        if (!is_array($item)) { http_response_code(400); exit('Invalid part'); }
        $description = trim((string)($item['description'] ?? ''));
        $qty = $item['qty'] ?? null; $price = $item['price'] ?? null;
        if ($description === '' || strlen($description) > 500 || !is_numeric($qty) || !is_numeric($price) || (float)$qty <= 0 || (float)$qty > 100000 || (float)$price < 0 || (float)$price > 10000000) { http_response_code(400); exit('Invalid part description, quantity or price'); }
        $qty = round((float)$qty,3); $price = round((float)$price,2);
        if ($qty <= 0) { http_response_code(400); exit('Invalid quantity'); }
        $cents += (int)round($qty * $price * 100);
        $clean[] = ['description'=>$description,'qty'=>$qty,'price'=>$price];
    }
    if ($cents > 999999999999) { http_response_code(400); exit('Parts total too large'); }
    return [$clean, number_format($cents/100,2,'.',''), implode("\n",array_map(static fn($x) => $x['description'].' (x'.$x['qty'].' @ R'.number_format($x['price'],2,'.','').')',$clean))];
}
