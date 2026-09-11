<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize all existing user phones to international format (+<dialCode><localNumber>).
     *
     * Users registered via the auth flow stored the local number in `phone`
     * and the dial code in `country_code` (e.g. phone="0191003606", country_code="+229").
     * Users created via the booking flow already stored the full international format.
     * This migration makes every row consistent: phone="+2290191003606".
     */
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone', 'country_code']);

        foreach ($users as $user) {
            $phone = $user->phone;

            // Already international — skip
            if (str_starts_with($phone, '+')) {
                continue;
            }

            // Strip leading 00 (alternate international prefix)
            if (str_starts_with($phone, '00')) {
                $phone = '+'.substr($phone, 2);
                DB::table('users')->where('id', $user->id)->update(['phone' => $phone]);

                continue;
            }

            // Combine country_code + phone
            $dialCode = '';
            if ($user->country_code !== null && $user->country_code !== '') {
                $dialCode = preg_replace('/[^0-9]/', '', $user->country_code);
            }

            if ($dialCode === '') {
                // No country code — default to Benin (229)
                $dialCode = '229';
            }

            $normalized = '+'.$dialCode.$phone;
            DB::table('users')->where('id', $user->id)->update(['phone' => $normalized]);
        }
    }

    /**
     * Reverse the migration.
     *
     * We cannot perfectly restore the original split format, so we leave
     * the phone column as-is. The country_code column is untouched.
     */
    public function down(): void
    {
        // No-op: the normalization is idempotent and the original split
        // format is not recoverable from the canonical form.
    }
};
