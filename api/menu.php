<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

function fallbackMenu(): void {
    $fallbackFile = __DIR__ . '/../assets/data/menu.json';
    if (file_exists($fallbackFile)) {
        $menu = json_decode(file_get_contents($fallbackFile), true, 512, JSON_THROW_ON_ERROR);
        foreach ($menu['items'] as &$item) {
            $item['recommended'] = false;
            if (($item['badge'] ?? '') === 'แนะนำ') $item['badge'] = '';
        }
        unset($item);
        jsonResponse($menu);
    }
    jsonResponse(['mode' => 'fallback', 'categories' => [], 'items' => []]);
}

if (getenv('POS_MODE') === 'demo') {
    fallbackMenu();
}

$pdo = Database::getConnection();
if (!$pdo) {
    fallbackMenu();
}

$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? getenv('DEFAULT_BRANCH_ID') ?? '001'));

try {
    function getCatIcon(string $name): string {
        if (str_contains($name, 'กาแฟ')) return 'cup';
        if (str_contains($name, 'ชา') || str_contains($name, 'โกโก้') || str_contains($name, 'มัทฉะ')) return 'teapot';
        if (str_contains($name, 'เบียร์') || str_contains($name, 'ไวน์')) return 'wine';
        if (str_contains($name, 'สมุนไพร')) return 'herbal';
        if (str_contains($name, 'โซดา') || str_contains($name, 'ผลไม้')) return 'fizzy';
        if (str_contains($name, 'น้ำดื่ม') || str_contains($name, 'น้ำแข็ง')) return 'water';
        if (str_contains($name, 'ขนม') || str_contains($name, 'หวาน')) return 'cake';
        if (str_contains($name, 'ทานเล่น') || str_contains($name, 'ของทอด')) return 'snack';
        if (str_contains($name, 'ยำ') || str_contains($name, 'สลัด')) return 'leaf';
        if (str_contains($name, 'เครื่องดื่ม') || str_contains($name, 'น้ำ')) return 'cup';
        return 'bowl';
    }

    function getItemImage(string $name, string $cat): string {
        if (str_contains($name, 'กาแฟ') || str_contains($name, 'ชา') || str_contains($name, 'น้ำ') || str_contains($name, 'โซดา') || str_contains($name, 'เบียร์') || str_contains($name, 'Americano') || str_contains($name, 'Latte') || str_contains($name, 'Tea') || str_contains($name, 'Cocoa') || str_contains($name, 'Soda') || str_contains($name, 'Berry')) return 'assets/images/drink.jpg';
        if (str_contains($name, 'ไอศกรีม') || str_contains($name, 'หวาน') || str_contains($name, 'สาคู') || str_contains($name, 'เฉาก๊วย') || str_contains($name, 'ถั่วดำ')) return 'assets/images/icecream.jpg';
        if (str_contains($name, 'สลัด') || str_contains($name, 'ยำ')) return 'assets/images/salad.jpg';
        if (str_contains($name, 'ก๋วย') || str_contains($name, 'เฝ่อ') || str_contains($name, 'ต้ม')) return 'assets/images/noodles.jpg';
        if (str_contains($name, 'แกง') || str_contains($name, 'คลุก') || str_contains($name, 'ผัด')) return 'assets/images/curry.jpg';
        return 'assets/images/pad-thai.jpg';
    }

    // 1. Menu items directly from Trade table
    $stmt = $pdo->prepare("SELECT Trade_ID, 
                                  CONVERT(varbinary(max), Trade_Name) as Trade_Name, 
                                  CONVERT(varbinary(max), Type_Name) as Type_Name, 
                                  CONVERT(varbinary(max), Unit_Name) as Unit_Name, 
                                  CONVERT(varbinary(max), Link_Part_image) as Link_Part_image,
                                  Sale_Price, Cost_Price, Food_Type 
                           FROM Trade 
                           WHERE Branch_ID = ? 
                           ORDER BY Trade_ID ASC");
    $stmt->execute([$branchId]);
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Categories come from Type; Trade stores the category name rather than Type_ID.
    $stmt = $pdo->prepare("SELECT Type_ID, CONVERT(varbinary(max), Type_Name) AS Type_Name
                           FROM [Type] WHERE Branch_ID = ? ORDER BY Type_ID ASC");
    $stmt->execute([$branchId]);
    $rawCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Only confirmed kitchen states and non-cancelled bills count as orders.
    $rank = [];
    $pairs = [];
    try {
        $stmt = $pdo->prepare("SELECT d.Trade_ID,
            SUM(CASE WHEN d.Fn_Doc_Date_Time >= DATEADD(day, -7, GETDATE()) THEN d.Qty ELSE 0 END) AS Q7,
            SUM(d.Qty) AS Q30, COUNT(DISTINCT d.Fn_Doc_No) AS O30,
            SUM(CASE WHEN d.Fn_Doc_Date_Time < DATEADD(day, -7, GETDATE()) THEN d.Qty ELSE 0 END) AS Prev23
            FROM Fnt_Detail d JOIN Fnt_Header h ON h.Branch_ID=d.Branch_ID AND h.Fn_Doc_No=d.Fn_Doc_No
            WHERE d.Branch_ID=? AND h.Doc_Sts IN (0,1)
              AND d.Fn_Doc_Date_Time >= DATEADD(day,-30,GETDATE()) AND d.Fn_Doc_Date_Time <= GETDATE()
              AND d.Qty > 0 AND d.Food_Que_STS IN ('S','P','W')
            GROUP BY d.Trade_ID");
        $stmt->execute([$branchId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rank[trim((string)$row['Trade_ID'])] = [
                'score' => (float)$row['Q7'] * .5 + (float)$row['Q30'] * .3 + (int)$row['O30'] * .2,
                'quantity' => (float)$row['Q30'],
                'recent' => (float)$row['Q7'],
                'trend' => ((float)$row['Q7'] / 7) / ((float)$row['Prev23'] / 23 + 1),
            ];
        }
        $stmt->closeCursor();
    } catch (Throwable $e) {
        error_log('Recommendation ranking unavailable: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("WITH bill_items AS (
            SELECT DISTINCT d.Fn_Doc_No, d.Trade_ID
            FROM Fnt_Detail d JOIN Fnt_Header h ON h.Branch_ID=d.Branch_ID AND h.Fn_Doc_No=d.Fn_Doc_No
            WHERE d.Branch_ID=? AND h.Doc_Sts IN (0,1) AND d.Food_Que_STS IN ('S','P','W') AND d.Qty>0
              AND d.Fn_Doc_Date_Time >= DATEADD(day,-90,GETDATE()) AND d.Fn_Doc_Date_Time <= GETDATE()
        ), item_counts AS (SELECT Trade_ID, COUNT(*) n FROM bill_items GROUP BY Trade_ID),
        totals AS (SELECT COUNT(DISTINCT Fn_Doc_No) n FROM bill_items),
        pairs AS (SELECT a.Trade_ID source_id, b.Trade_ID target_id, COUNT(*) n
            FROM bill_items a JOIN bill_items b ON a.Fn_Doc_No=b.Fn_Doc_No AND a.Trade_ID<>b.Trade_ID
            GROUP BY a.Trade_ID,b.Trade_ID HAVING COUNT(*)>=10)
        SELECT p.source_id, p.target_id, p.n, 1.0*p.n/a.n confidence, 1.0*p.n/t.n support,
               1.0*p.n*t.n/NULLIF(1.0*a.n*b.n,0) lift
        FROM pairs p JOIN item_counts a ON a.Trade_ID=p.source_id JOIN item_counts b ON b.Trade_ID=p.target_id CROSS JOIN totals t
        WHERE 1.0*p.n/NULLIF(a.n,0)>=0.4 AND 1.0*p.n/NULLIF(t.n,0)>=0.02 AND 1.0*p.n*t.n/NULLIF(1.0*a.n*b.n,0)>1");
        $stmt->execute([$branchId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pairs[] = ['source' => trim((string)$row['source_id']), 'target' => trim((string)$row['target_id']), 'confidence' => (float)$row['confidence'], 'support' => (float)$row['support'], 'lift' => (float)$row['lift'], 'count' => (int)$row['n']];
        }
        $stmt->closeCursor();
    } catch (Throwable $e) {
        error_log('Recommendation pairs unavailable: ' . $e->getMessage());
    }

    $categories = [
        ['id' => 'all', 'name' => 'ทั้งหมด', 'icon' => 'grid'],
    ];

    $catMap = [];
    foreach ($rawCategories as $category) {
        $name = trim(Database::decodeThai($category['Type_Name']));
        $typeId = trim((string)$category['Type_ID']);
        if ($name === '' || $typeId === '') continue;
        $catId = 'type_' . $typeId;
        $catMap[$name] = $catId;
        $categories[] = ['id' => $catId, 'name' => $name, 'icon' => getCatIcon($name)];
    }
    $items = [];

    foreach ($rawItems as $r) {
        $cName = trim(Database::decodeThai($r['Type_Name'])) ?: 'อาหารทั่วไป';

        $tName = Database::decodeThai($r['Trade_Name']);
        $uName = Database::decodeThai($r['Unit_Name']) ?: 'จาน';
        $linkImg = Database::decodeThai($r['Link_Part_image']);
        $catId = $catMap[$cName] ?? '';

        $itemRank = $rank[trim((string)$r['Trade_ID'])] ?? null;
        $isRec = $itemRank !== null;
        $price = (float)$r['Sale_Price'];
        $img = $linkImg ?: getItemImage($tName, $cName);

        $items[] = [
            'id' => $r['Trade_ID'],
            'name' => $tName,
            'unit' => $uName,
            'foodType' => $r['Food_Type'] ?? 'FOOD',
            'english' => ($r['Food_Type'] ?? '') === 'DRINK' ? 'Beverage' : 'Dish',
            'category' => $catId,
            'price' => $price,
            'image' => $img,
            'description' => "เมนูสดใหม่ ปรุงจากวัตถุดิบคุณภาพ เสิร์ฟร้อน ๆ สำหรับมื้อพิเศษของคุณ",
            'recommended' => $isRec,
            'recommendationScore' => $itemRank['score'] ?? 0,
            'recommendationQuantity' => $itemRank['quantity'] ?? 0,
            'trendScore' => $itemRank['trend'] ?? 0,
            'trendRecentQuantity' => $itemRank['recent'] ?? 0,
            'badge' => $isRec ? 'แนะนำ' : '',
            'available' => true,
            'allergens' => 'สอบถามข้อมูลสารก่อภูมิแพ้กับพนักงาน',
        ];
    }

    jsonResponse([
        'mode' => 'live',
        'branch' => $branchId,
        'categories' => $categories,
        'items' => $items,
        'recommendationPairs' => $pairs,
        'totalItems' => count($items),
    ]);
} catch (Throwable $e) {
    error_log("Error in api/menu.php: " . $e->getMessage());
    fallbackMenu();
}
