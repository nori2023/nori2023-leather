<?php
declare(strict_types=1);

const PRODUCT_SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
       s.name AS shipping_name, s.fee AS shipping_fee
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN shipping_profiles s ON s.id = p.shipping_profile_id';

function attach_images(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_column($rows, 'id');
    $imgs = db_all(
        'SELECT * FROM product_images WHERE product_id IN (' . db_in($ids) . ')
         ORDER BY is_main DESC, sort_order ASC, created_at ASC',
        $ids,
    );
    $by = [];
    foreach ($imgs as $img) {
        $by[$img['product_id']][] = $img;
    }
    foreach ($rows as &$r) {
        $r['images'] = $by[$r['id']] ?? [];
        $r['price'] = (int) $r['price'];
        $r['shipping_fee'] = $r['shipping_fee'] === null ? null : (int) $r['shipping_fee'];
    }
    return $rows;
}

function list_public_products(array $statuses = PUBLIC_STATUSES, ?string $categorySlug = null, int $limit = 200): array
{
    $statuses = array_values(array_intersect($statuses, PUBLIC_STATUSES));
    $where = ['p.status IN (' . db_in($statuses) . ')'];
    $params = $statuses;
    if ($categorySlug) {
        $where[] = 'c.slug = ?';
        $params[] = $categorySlug;
    }
    $order = $statuses === ['sold_out'] ? 'p.sold_at DESC' : 'COALESCE(p.published_at, p.created_at) DESC';
    $limit = max(1, min(500, $limit));
    return attach_images(db_all(PRODUCT_SELECT . ' WHERE ' . implode(' AND ', $where) . " ORDER BY {$order} LIMIT {$limit}", $params));
}

function get_public_product(string $slug): ?array
{
    $row = db_one(PRODUCT_SELECT . " WHERE p.slug = ? AND p.status IN ('on_sale','reserved','sold_out')", [$slug]);
    return $row ? attach_images([$row])[0] : null;
}

function get_product(string $id): ?array
{
    $row = db_one(PRODUCT_SELECT . ' WHERE p.id = ?', [$id]);
    return $row ? attach_images([$row])[0] : null;
}

function list_all_products(?string $status, ?string $q, string $sort = 'updated'): array
{
    $where = [];
    $params = [];
    if ($status && isset(STATUS_LABEL[$status])) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    if ($q) {
        $where[] = '(p.name LIKE ? OR p.code LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $order = match ($sort) {
        'code' => 'LENGTH(p.code), p.code',
        'code_desc' => 'LENGTH(p.code) DESC, p.code DESC',
        default => 'p.updated_at DESC',
    };
    $sql = PRODUCT_SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $order . ' LIMIT 500';
    return attach_images(db_all($sql, $params));
}

/**
 * 最後の品番と、次に使う品番の候補。
 * いちばん新しく登録した商品の品番の形（例：nori2023-0004 → 「nori2023-」＋4桁）をもとに、
 * 同じ形の中で番号がいちばん大きいものを「最後の品番」とする。
 */
function product_code_status(): array
{
    $count = (int) db_value('SELECT COUNT(*) FROM products');
    $latest = db_value('SELECT code FROM products ORDER BY created_at DESC LIMIT 1');
    if (!$latest || !preg_match('/^(.*?)(\d+)$/', (string) $latest, $m)) {
        return ['last' => $latest ?: null, 'next' => null, 'count' => $count];
    }
    $prefix = $m[1];
    $digits = strlen($m[2]);
    $best = ['code' => $latest, 'n' => (int) $m[2]];
    foreach (db_all('SELECT code FROM products WHERE code LIKE ?', [addcslashes($prefix, '%_\\') . '%']) as $r) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $r['code'], $mm) && (int) $mm[1] > $best['n']) {
            $best = ['code' => $r['code'], 'n' => (int) $mm[1]];
        }
    }
    $n = $best['n'];
    do {
        $n++;
        $next = $prefix . str_pad((string) $n, $digits, '0', STR_PAD_LEFT);
    } while (db_value('SELECT COUNT(*) FROM products WHERE code = ?', [$next]));
    return ['last' => $best['code'], 'next' => $next, 'count' => $count];
}

function list_categories(): array
{
    return db_all('SELECT * FROM categories ORDER BY sort_order, name');
}

function list_shipping_profiles(bool $activeOnly = false): array
{
    return db_all('SELECT * FROM shipping_profiles' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY created_at');
}

function list_instagram_posts(int $limit = 6): array
{
    $limit = max(1, min(24, $limit));
    return db_all("SELECT * FROM instagram_posts WHERE image_key IS NOT NULL ORDER BY posted_at DESC LIMIT {$limit}");
}
