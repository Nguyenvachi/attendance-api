<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * BỔ SUNG: Encrypt sensitive data đã tồn tại trong database
     * QUAN TRỌNG: Backup database trước khi chạy migration này!
     *
     * @return void
     */
    public function up()
    {
        // Đảm bảo APP_KEY đã được set
        if (empty(config('app.key'))) {
            throw new \Exception('APP_KEY chưa được set. Chạy: php artisan key:generate');
        }

        DB::transaction(function () {
            // Encrypt nfc_token_hash (nếu có)
            $users = DB::table('users')
                ->whereNotNull('nfc_token_hash')
                ->where('nfc_token_hash', '!=', '')
                ->get();

            foreach ($users as $user) {
                // Chỉ encrypt nếu chưa bị encrypt (check bằng cách decrypt thử)
                try {
                    Crypt::decryptString($user->nfc_token_hash);
                    // Nếu decrypt thành công -> đã encrypt rồi, skip
                    continue;
                } catch (\Exception $e) {
                    // Decrypt fail -> chưa encrypt, tiến hành encrypt
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'nfc_token_hash' => Crypt::encryptString($user->nfc_token_hash),
                        ]);
                }
            }

            // Encrypt biometric_id (nếu có)
            $users = DB::table('users')
                ->whereNotNull('biometric_id')
                ->where('biometric_id', '!=', '')
                ->get();

            foreach ($users as $user) {
                try {
                    Crypt::decryptString($user->biometric_id);

                    continue;
                } catch (\Exception $e) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'biometric_id' => Crypt::encryptString($user->biometric_id),
                        ]);
                }
            }
        });

        Log::info('Sensitive data encrypted successfully');
    }

    /**
     * Reverse the migrations.
     * Decrypt về plaintext (chỉ dùng khi rollback)
     *
     * @return void
     */
    public function down()
    {
        DB::transaction(function () {
            // Decrypt nfc_token_hash
            $users = DB::table('users')
                ->whereNotNull('nfc_token_hash')
                ->where('nfc_token_hash', '!=', '')
                ->get();

            foreach ($users as $user) {
                try {
                    $decrypted = Crypt::decryptString($user->nfc_token_hash);
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['nfc_token_hash' => $decrypted]);
                } catch (\Exception $e) {
                    // Nếu decrypt fail -> đã là plaintext rồi
                    continue;
                }
            }

            // Decrypt biometric_id
            $users = DB::table('users')
                ->whereNotNull('biometric_id')
                ->where('biometric_id', '!=', '')
                ->get();

            foreach ($users as $user) {
                try {
                    $decrypted = Crypt::decryptString($user->biometric_id);
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['biometric_id' => $decrypted]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        });

        Log::info('Sensitive data decrypted (rollback)');
    }
};
