<?php
// app/models/CategoryModel.php
class CategoryModel extends Model {
    protected string $table = 'categories';

    public function getTree(): array {
        $all  = $this->db->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY level,sort_order,name");
        $tree = [];
        $map  = [];
        foreach ($all as $cat) $map[$cat['id']] = $cat + ['children' => []];
        foreach ($map as &$cat) {
            if ($cat['parent_id']) $map[$cat['parent_id']]['children'][] = &$cat;
            else $tree[] = &$cat;
        }
        return $tree;
    }

    public function getTopLevel(): array {
        return $this->db->fetchAll("SELECT * FROM categories WHERE parent_id IS NULL AND is_active=1 ORDER BY sort_order");
    }

    public function getChildren(int $parentId): array {
        return $this->db->fetchAll("SELECT * FROM categories WHERE parent_id=? AND is_active=1 ORDER BY sort_order", [$parentId]);
    }

    public function getBySlug(string $slug): ?array {
        return $this->db->fetchOne("SELECT * FROM categories WHERE slug=?", [$slug]);
    }

    public function getSelfAndDescendantIds(int $categoryId): array {
        if ($categoryId <= 0) {
            return [];
        }

        $allCategories = $this->db->fetchAll(
            "SELECT id, parent_id FROM categories WHERE is_active=1 ORDER BY level,sort_order,name"
        );

        if (empty($allCategories)) {
            return [];
        }

        $childrenByParent = [];
        $existingIds = [];

        foreach ($allCategories as $category) {
            $id = (int)($category['id'] ?? 0);
            $parentId = isset($category['parent_id']) ? (int)$category['parent_id'] : 0;
            $existingIds[$id] = true;
            $childrenByParent[$parentId][] = $id;
        }

        if (!isset($existingIds[$categoryId])) {
            return [];
        }

        $ids = [];
        $stack = [$categoryId];

        while (!empty($stack)) {
            $currentId = (int)array_pop($stack);
            if (isset($ids[$currentId])) {
                continue;
            }

            $ids[$currentId] = true;

            foreach (($childrenByParent[$currentId] ?? []) as $childId) {
                if (!isset($ids[$childId])) {
                    $stack[] = (int)$childId;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }
}
