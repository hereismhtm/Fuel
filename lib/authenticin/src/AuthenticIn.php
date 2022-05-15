<?php

namespace AuthenticIn;

use App\Models\AuthenticInUser;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Illuminate\Http\Request;
use Medoo\Medoo;
use Toolly\Kee;
use Toolly\Typ;

class AuthenticIn
{
    public static string $otp_secret_value;

    private const GUARD_LENGTH = 16;
    private static ?Key $defuse_key = null;
    private Medoo $db;

    public function __construct(Medoo &$db)
    {
        $this->db = $db;
    }

    public function identifyUser(Request $request): AuthenticInUser
    {
        $authorization = substr($request->header('Authorization'), 6);
        $authorization = base64_decode($authorization);
        $authorization = explode(':', $authorization);

        $auth = [];
        if (count($authorization) == 3) {
            $auth['id'] = $authorization[0];
            $auth['counter'] = $authorization[1];
            $auth['otp'] = $authorization[2];
        }

        if (Typ::validate($auth, [
            'id' => Typ::i(),
            'counter' => Typ::i(),
            'otp' => Typ::hex_(40),
        ])) {
            $user = $this->userHolder($auth['id']);
            $user->login($auth['counter'], $auth['otp']);
            return $user;
        } else {
            return $this->userHolder(id: null);
        }
    }

    /**
     * Get `AuthenticInUser` object.
     */
    public function userHolder(?int $id = null): AuthenticInUser
    {
        return new AuthenticInUser($id, $this->db);
    }

    public static function otp(int $counter, string $otp_secret): string
    {
        return hash('SHA1', $counter . $otp_secret);
    }

    public static function guard(string $guarded_data, string $otp_secret): string
    {
        $guard = hash(
            'SHA512',
            $guarded_data . $otp_secret . env('AUTHENTICIN_KEY')
        );

        return substr($guard, 0, self::GUARD_LENGTH);
    }

    public static function generateSafeguard(string $guarded_data, ?string $otp_secret = null): string
    {
        if ($otp_secret === null) {
            $otp_secret = Kee::createSecret(14);
            AuthenticIn::$otp_secret_value = $otp_secret;
        }
        $guard = AuthenticIn::guard($guarded_data, $otp_secret);
        $text = "{$guard}{$otp_secret}";

        return Crypto::encrypt($text, AuthenticIn::defuseKey());
    }

    public static function extractSafeguard(string $safeguard)
    {
        $text = AuthenticIn::decryptSafeguard($safeguard);
        if ($text === false) {
            return false;
        }
        $guard = substr($text, 0, self::GUARD_LENGTH);
        $otp_secret = substr($text, self::GUARD_LENGTH);

        return [(string) $guard, (string) $otp_secret];
    }

    private static function decryptSafeguard(string $safeguard): string|false
    {
        try {
            return Crypto::decrypt($safeguard, AuthenticIn::defuseKey());
        } catch (WrongKeyOrModifiedCiphertextException) {
            /** Not creditable model data OR not valid safeguard! */
            return false;
        }
    }

    private static function defuseKey(): Key
    {
        if (AuthenticIn::$defuse_key === null) {
            $keyAscii = file_get_contents(getenv('AUTHENTICIN_ETC') . '/authenticin_defuse.key');
            AuthenticIn::$defuse_key = Key::loadFromAsciiSafeString($keyAscii);
        }
        return AuthenticIn::$defuse_key;
    }
}
