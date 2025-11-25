<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveTextureColumn extends AbstractMigration
{
    public function up(): void
    {
        $this->table('user')
            ->removeColumn('texture')
            ->update();
    }

    public function down(): void
    {
        // Re-add the column if we rollback
        $this->table('user')
            ->addColumn('texture', 'string', [
                'limit' => 100,
                'default' => '222222221111111111111111111111111111111111111111111',
                'null' => false,
            ])
            ->update();

        // Restore data from bitmask
        $rows = $this->fetchAll('SELECT id, boards_bitmask FROM user');

        foreach ($rows as $row) {
            $bitmask = (int) $row['boards_bitmask'];
            $texture = '';

            // Reconstruct string. Default length is 51 based on the default value.
            for ($i = 0; $i < 51; $i++) {
                if ($bitmask & (1 << $i)) {
                    $texture .= '2';
                } else {
                    $texture .= '1';
                }
            }

            $this->execute("UPDATE user SET texture = '$texture' WHERE id = {$row['id']}");
    }
}
}

