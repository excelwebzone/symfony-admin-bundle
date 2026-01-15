<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TableLayoutExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('normalize_columns', [$this, 'normalizeColumns']),
        ];
    }

    /**
     * @param array|null $saved
     * @param array|null $defaults
     *
     * return array
     */
    public function normalizeColumns(array $saved = null, array $defaults = null): array
    {
        $saved = $saved ?? [];
        $defaults = $defaults ?? [];

        // if defaults is a plain list (not keyed), normalize as a list
        if ($this->isColumnList($defaults)) {
            return $this->normalizeColumnList(
                $this->isColumnList($saved) ? $saved : [],
                $defaults
            );
        }

        // defaults is a keyed map (e.g., { right: [...], left: [...] })
        $out = [];

        foreach ($defaults as $key => $defaultList) {
            if (!\is_array($defaultList)) {
                $out[$key] = $defaultList;
                continue;
            }

            $savedList = (isset($saved[$key]) && \is_array($saved[$key])) ? $saved[$key] : [];

            // if the list is not in the expected column-list shape, treat as empty saved
            if (!$this->isColumnList($defaultList)) {
                $out[$key] = $defaultList;
                continue;
            }

            $out[$key] = $this->normalizeColumnList($savedList, $defaultList);
        }

        return $out;
    }

    /**
     * @param array|null $saved
     * @param array|null $defaults
     *
     * return array
     */
    private function normalizeColumnList(array $saved, array $defaults): array
    {
        if (empty($saved)) {
            return $defaults;
        }

        $defaultByField = [];
        $defaultOrder = [];

        foreach ($defaults as $def) {
            if (!\is_array($def)) {
                continue;
            }
            $field = $def['field'] ?? null;
            if (!\is_string($field) || '' === $field) {
                continue;
            }
            $defaultByField[$field] = $def;
            $defaultOrder[] = $field;
        }

        if (empty($defaultOrder)) {
            return $saved;
        }

        $out = [];
        $seen = [];

        foreach ($saved as $col) {
            if (!\is_array($col)) {
                continue;
            }
            $field = $col['field'] ?? null;
            if (!\is_string($field) || '' === $field) {
                continue;
            }
            if (!isset($defaultByField[$field])) {
                continue; // drop removed/unknown
            }

            $out[] = array_merge($defaultByField[$field], $col);
            $seen[$field] = true;
        }

        foreach ($defaultOrder as $field) {
            if (!isset($seen[$field])) {
                $out[] = $defaultByField[$field];
            }
        }

        return $out;
    }

    /**
     * @param mixed $level
     *
     * return bool
     */
    private function isColumnList($value): bool
    {
        if (!\is_array($value)) {
            return false;
        }

        // empty list counts as a column list (so defaults can be empty)
        if ([] === $value) {
            return true;
        }

        // a "column list" is a numeric array of arrays that contain 'field'
        $keys = array_keys($value);
        $isNumeric = ($keys === range(0, \count($keys) - 1));

        if (!$isNumeric) {
            return false;
        }

        foreach ($value as $item) {
            if (!\is_array($item) || !\array_key_exists('field', $item)) {
                return false;
            }
        }

        return true;
    }
}
