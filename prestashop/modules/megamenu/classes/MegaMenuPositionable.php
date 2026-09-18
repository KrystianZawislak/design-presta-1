<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

trait MegaMenuPositionable
{
    protected function moveOne(string $table, string $primary, int $direction, ?string $scopeColumn = null, ?int $scopeValue = null): bool
    {
        $operator = $direction < 0 ? '<' : '>';
        $order = $direction < 0 ? 'DESC' : 'ASC';
        $scopeSql = $scopeColumn !== null ? ' AND `' . $scopeColumn . '` = ' . (int) $scopeValue . ' ' : '';

        $neighbor = Db::getInstance()->getRow(
            'SELECT `' . $primary . '`, `position` FROM `' . _DB_PREFIX_ . $table . '`
             WHERE `position` ' . $operator . ' ' . (int) $this->position . $scopeSql . '
             ORDER BY `position` ' . $order
        );

        if (!$neighbor) {
            return false;
        }

        Db::getInstance()->update($table, ['position' => (int) $neighbor['position']], '`' . $primary . '` = ' . (int) $this->id);
        Db::getInstance()->update($table, ['position' => (int) $this->position], '`' . $primary . '` = ' . (int) $neighbor[$primary]);

        return true;
    }

    protected static function nextPositionInScope(string $table, ?string $scopeColumn = null, ?int $scopeValue = null): int
    {
        $scopeSql = $scopeColumn !== null ? ' WHERE `' . $scopeColumn . '` = ' . (int) $scopeValue . ' ' : '';
        $max = Db::getInstance()->getValue('SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . $table . '`' . $scopeSql);

        return $max === false || $max === null ? 0 : (int) $max + 1;
    }
}
