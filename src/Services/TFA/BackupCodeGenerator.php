<?php


namespace App\Services\TFA;

/**
 * This class generates random backup codes for two factor authentication
 * @package App\Services\TFA
 */
class BackupCodeGenerator
{
    protected $code_length;
    protected $code_count;

    /**
     * BackupCodeGenerator constructor.
     * @param  int  $code_length How many characters a single code should have.
     * @param  int  $code_count How many codes are generated for a whole backup set.
     */
    public function __construct(int $code_length, int $code_count)
    {
        if ($code_length > 32) {
            throw new \RuntimeException('Backup code can have maximum 32 digits!');
        }
        if ($code_length < 6) {
            throw new \RuntimeException('Code must have at least 6 digits to ensure security!');
        }

        $this->code_count = $code_count;
        $this->code_length = $code_length;
    }

    /**
     * Generates a single backup code.
     * It is a random hexadecimal value with the digit count configured in constructor
     * @return string The generated backup code (e.g. 1f3870be2)
     * @throws \Exception If no entropy source is available.
     */
    public function generateSingleCode() : string
    {
        $bytes = random_bytes(32);
        return substr(md5($bytes), 0, $this->code_length);
    }


    /**
     * Returns a full backup code set. The code count can be configured in the constructor
     * @return string[] An array containing different backup codes.
     * @throws \Exception If no entropy source is available
     */
    public function generateCodeSet() : array
    {
        $array = [];
        for($n=0; $n<$this->code_count; $n++) {
            $array[] = $this->generateSingleCode();
        }

        return $array;
    }
}