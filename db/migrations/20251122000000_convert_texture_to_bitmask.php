<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ConvertTextureToBitmask extends AbstractMigration
{
    public function up(): void
    {
        // 1. Add the new column
        $this->table('user')
            ->addColumn('boards_bitmask', 'biginteger', [
                'default' => 0,
                'signed' => false, // Unsigned for full 64-bit usage if needed, though 63 bits fits in signed too.
                'null' => false,
                'comment' => 'Bitmask representing enabled boards. Bit N corresponds to Board N+1.'
            ])
            ->update();

        // 2. Migrate data
        // Fetch all users with a non-empty texture
        $rows = $this->fetchAll('SELECT id, texture FROM user WHERE texture IS NOT NULL AND texture != ""');

        foreach ($rows as $row) {
            $texture = $row['texture'];
            $bitmask = 0;
            $length = strlen($texture);

            // Limit to 64 bits to be safe, though texture is expected to be ~51 chars
            $limit = min($length, 63);

            for ($i = 0; $i < $limit; $i++) {
                // '2' means enabled/checked in the legacy string format.
                // '1' means disabled. '0' is a special value for "use defaults" in the cookie, treated as disabled here.
                if ($texture[$i] === '2') {
                    $bitmask |= (1 << $i);
                }
            }

            // Update the user
            $this->execute("UPDATE user SET boards_bitmask = $bitmask WHERE id = {$row['id']}");
        }
    }

    public function down(): void
    {
        // Restore data from bitmask to texture before dropping the column
        // This ensures that if users modified their settings while on the bitmask version,
        // those changes are preserved when rolling back.
        $rows = $this->fetchAll('SELECT id, boards_bitmask FROM user');

        foreach ($rows as $row) {
            $bitmask = (int) $row['boards_bitmask'];
            $texture = '';

            // Reconstruct string. Default length is 51.
            for ($i = 0; $i < 51; $i++) {
                if ($bitmask & (1 << $i)) {
                    $texture .= '2';
                } else {
                    $texture .= '1';
                }
            }

            $this->execute("UPDATE user SET texture = '$texture' WHERE id = {$row['id']}");
        }

        $this->table('user')
            ->removeColumn('boards_bitmask')
            ->update();
    }
}

