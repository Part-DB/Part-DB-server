<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240728145604 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Update the Natural Sorting function for MySQL';
    }


    public function mySQLUp(Schema $schema): void
    {
        //Remove the old function
        $this->addSql('DROP FUNCTION IF EXISTS NatSortKey');

        //The difference to the original function is the correct length of the suf variable and correct escaping
        //We now use heredoc syntax to avoid escaping issues with the \ (which resulted in "range out of order in character class").
        $this->addSql(<<<'EOD'
            CREATE DEFINER=CURRENT_USER FUNCTION `NatSortKey`(`s` VARCHAR(1000) CHARSET utf8mb4, `n` INT) RETURNS varchar(3500) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
            DETERMINISTIC
            SQL SECURITY INVOKER
            BEGIN
            /****
            Converts numbers in the input string s into a format such that sorting results in a nat-sort.
            Numbers of up to 359 digits (before the decimal point, if one is present) are supported.  Sort results are undefined if the input string contains numbers longer than this.
            For n>0, only the first n numbers in the input string will be converted for nat-sort (so strings that differ only after the first n numbers will not nat-sort amongst themselves).
            Total sort-ordering is preserved, i.e. if s1!=s2, then NatSortKey(s1,n)!=NatSortKey(s2,n), for any given n.
            Numbers may contain ',' as a thousands separator, and '.' as a decimal point.  To reverse these (as appropriate for some European locales), the code would require modification.
            Numbers preceded by '+' sort with numbers not preceded with either a '+' or '-' sign.
            Negative numbers (preceded with '-') sort before positive numbers, but are sorted in order of ascending absolute value (so -7 sorts BEFORE -1001).
            Numbers with leading zeros sort after the same number with no (or fewer) leading zeros.
            Decimal-part-only numbers (like .75) are recognised, provided the decimal point is not immediately preceded by either another '.', or by a letter-type character.
            Numbers with thousand separators sort after the same number without them.
            Thousand separators are only recognised in numbers with no leading zeros that don't immediately follow a ',', and when they format the number correctly.
            (When not recognised as a thousand separator, a ',' will instead be treated as separating two distinct numbers).
            Version-number-like sequences consisting of 3 or more numbers separated by '.' are treated as distinct entities, and each component number will be nat-sorted.
            The entire entity will sort after any number beginning with the first component (so e.g. 10.2.1 sorts after both 10 and 10.995, but before 11)
            Note that The first number component in an entity like this is also permitted to contain thousand separators.
        
                  To achieve this, numbers within the input string are prefixed and suffixed according to the following format:
                  - The number is prefixed by a 2-digit base-36 number representing its length, excluding leading zeros.  If there is a decimal point, this length only includes the integer part of the number.
                  - A 3-character suffix is appended after the number (after the decimals if present).
                    - The first character is a space, or a '+' sign if the number was preceded by '+'.  Any preceding '+' sign is also removed from the front of the number.
                    - This is followed by a 2-digit base-36 number that encodes the number of leading zeros and whether the number was expressed in comma-separated form (e.g. 1,000,000.25 vs 1000000.25)
                    - The value of this 2-digit number is: (number of leading zeros)*2 + (1 if comma-separated, 0 otherwise)
                  - For version number sequences, each component number has the prefix in front of it, and the separating dots are removed.
                    Then there is a single suffix that consists of a ' ' or '+' character, followed by a pair base-36 digits for each number component in the sequence.
                
                  e.g. here is how some simple sample strings get converted:
                  'Foo055' --> 'Foo0255 02'
                  'Absolute zero is around -273 centigrade' --> 'Absolute zero is around -03273 00 centigrade'
                  'The $1,000,000 prize' --> 'The $071000000 01 prize'
                  '+99.74 degrees' --> '0299.74+00 degrees'
                  'I have 0 apples' --> 'I have 00 02 apples'
                  '.5 is the same value as 0000.5000' --> '00.5 00 is the same value as 00.5000 08'
                  'MariaDB v10.3.0018' --> 'MariaDB v02100130218 000004'
                
                  The restriction to numbers of up to 359 digits comes from the fact that the first character of the base-36 prefix MUST be a decimal digit, and so the highest permitted prefix value is '9Z' or 359 decimal.
                  The code could be modified to handle longer numbers by increasing the size of (both) the prefix and suffix.
                  A higher base could also be used (by replacing CONV() with a custom function), provided that the collation you are using sorts the "digits" of the base in the correct order, starting with 0123456789.
                  However, while the maximum number length may be increased this way, note that the technique this function uses is NOT applicable where strings may contain numbers of unlimited length.
                
                  The function definition does not specify the charset or collation to be used for string-type parameters or variables:  The default database charset & collation at the time the function is defined will be used.
                  This is to make the function code more portable.  However, there are some important restrictions:
                
                  - Collation is important here only when comparing (or storing) the output value from this function, but it MUST order the characters " +0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ" in that order for the natural sort to work.
                    This is true for most collations, but not all of them, e.g. in Lithuanian 'Y' comes before 'J' (according to Wikipedia).
                    To adapt the function to work with such collations, replace CONV() in the function code with a custom function that emits "digits" above 9 that are characters ordered according to the collation in use.
                
                  - For efficiency, the function code uses LENGTH() rather than CHAR_LENGTH() to measure the length of strings that consist only of digits 0-9, '.', and ',' characters.
                    This works for any single-byte charset, as well as any charset that maps standard ASCII characters to single bytes (such as utf8 or utf8mb4).
                    If using a charset that maps these characters to multiple bytes (such as, e.g. utf16 or utf32), you MUST replace all instances of LENGTH() in the function definition with CHAR_LENGTH()
                
                  Length of the output:
                
                  Each number converted adds 5 characters (2 prefix + 3 suffix) to the length of the string. n is the maximum count of numbers to convert;
                  This parameter is provided as a means to limit the maximum output length (to input length + 5*n).
                  If you do not require the total-ordering property, you could edit the code to use suffixes of 1 character (space or plus) only; this would reduce the maximum output length for any given n.
                  Since a string of length L has at most ((L+1) DIV 2) individual numbers in it (every 2nd character a digit), for n<=0 the maximum output length is (inputlength + 5*((inputlength+1) DIV 2))
                  So for the current input length of 100, the maximum output length is 350.
                  If changing the input length, the output length must be modified according to the above formula.  The DECLARE statements for x,y,r, and suf must also be modified, as the code comments indicate.
                ****/
                
          DECLARE x,y varchar(1000);            # need to be same length as input s
          DECLARE r varchar(3500) DEFAULT '';   # return value:  needs to be same length as return type
          DECLARE suf varchar(1001);   # suffix for a number or version string. Must be (((inputlength+1) DIV 2)*2 + 1) chars to support version strings (e.g. '1.2.33.5'), though it's usually just 3 chars. (Max version string e.g. 1.2. ... .5 has ((length of input + 1) DIV 2) numeric components)
          DECLARE i,j,k int UNSIGNED;
          IF n<=0 THEN SET n := -1; END IF;   # n<=0 means "process all numbers"
          LOOP
            SET i := REGEXP_INSTR(s,'\\d');   # find position of next digit
            IF i=0 OR n=0 THEN RETURN CONCAT(r,s); END IF;   # no more numbers to process -> we're done
            SET n := n-1, suf := ' ';
            IF i>1 THEN
              IF SUBSTRING(s,i-1,1)='.' AND (i=2 OR SUBSTRING(s,i-2,1) RLIKE '[^.\\p{L}\\p{N}\\p{M}\\x{608}\\x{200C}\\x{200D}\\x{2100}-\\x{214F}\\x{24B6}-\\x{24E9}\\x{1F130}-\\x{1F149}\\x{1F150}-\\x{1F169}\\x{1F170}-\\x{1F189}]') AND (SUBSTRING(s,i) NOT RLIKE '^\\d++\\.\\d') THEN SET i:=i-1; END IF;   # Allow decimal number (but not version string) to begin with a '.', provided preceding char is neither another '.', nor a member of the unicode character classes: "Alphabetic", "Letter", "Block=Letterlike Symbols" "Number", "Mark", "Join_Control"
              IF i>1 AND SUBSTRING(s,i-1,1)='+' THEN SET suf := '+', j := i-1; ELSE SET j := i; END IF;   # move any preceding '+' into the suffix, so equal numbers with and without preceding "+" signs sort together
              SET r := CONCAT(r,SUBSTRING(s,1,j-1)); SET s = SUBSTRING(s,i);   # add everything before the number to r and strip it from the start of s; preceding '+' is dropped (not included in either r or s)
            END IF;
            SET x := REGEXP_SUBSTR(s,IF(SUBSTRING(s,1,1) IN ('0','.') OR (SUBSTRING(r,-1)=',' AND suf=' '),'^\\d*+(?:\\.\\d++)*','^(?:[1-9]\\d{0,2}(?:,\\d{3}(?!\\d))++|\\d++)(?:\\.\\d++)*+'));   # capture the number + following decimals (including multiple consecutive '.<digits>' sequences)
            SET s := SUBSTRING(s,CHAR_LENGTH(x)+1);   # NOTE: CHAR_LENGTH() can be safely used instead of CHAR_LENGTH() here & below PROVIDED we're using a charset that represents digits, ',' and '.' characters using single bytes (e.g. latin1, utf8)
            SET i := INSTR(x,'.');
            IF i=0 THEN SET y := ''; ELSE SET y := SUBSTRING(x,i); SET x := SUBSTRING(x,1,i-1); END IF;   # move any following decimals into y
            SET i := CHAR_LENGTH(x);
            SET x := REPLACE(x,',','');
            SET j := CHAR_LENGTH(x);
            SET x := TRIM(LEADING '0' FROM x);   # strip leading zeros
            SET k := CHAR_LENGTH(x);
            SET suf := CONCAT(suf,LPAD(CONV(LEAST((j-k)*2,1294) + IF(i=j,0,1),10,36),2,'0'));   # (j-k)*2 + IF(i=j,0,1) = (count of leading zeros)*2 + (1 if there are thousands-separators, 0 otherwise)  Note the first term is bounded to <= base-36 'ZY' as it must fit within 2 characters
            SET i := LOCATE('.',y,2);
            IF i=0 THEN
              SET r := CONCAT(r,LPAD(CONV(LEAST(k,359),10,36),2,'0'),x,y,suf);   # k = count of digits in number, bounded to be <= '9Z' base-36
            ELSE   # encode a version number (like 3.12.707, etc)
              SET r := CONCAT(r,LPAD(CONV(LEAST(k,359),10,36),2,'0'),x);   # k = count of digits in number, bounded to be <= '9Z' base-36
              WHILE CHAR_LENGTH(y)>0 AND n!=0 DO
                IF i=0 THEN SET x := SUBSTRING(y,2); SET y := ''; ELSE SET x := SUBSTRING(y,2,i-2); SET y := SUBSTRING(y,i); SET i := LOCATE('.',y,2); END IF;
                SET j := CHAR_LENGTH(x);
                SET x := TRIM(LEADING '0' FROM x);   # strip leading zeros
                SET k := CHAR_LENGTH(x);
                SET r := CONCAT(r,LPAD(CONV(LEAST(k,359),10,36),2,'0'),x);   # k = count of digits in number, bounded to be <= '9Z' base-36
                SET suf := CONCAT(suf,LPAD(CONV(LEAST((j-k)*2,1294),10,36),2,'0'));   # (j-k)*2 = (count of leading zeros)*2, bounded to fit within 2 base-36 digits
                SET n := n-1;
              END WHILE;
              SET r := CONCAT(r,y,suf);
            END IF;
          END LOOP;
        END
        EOD
        );
    }

    public function mySQLDown(Schema $schema): void
    {
        //Not needed
    }

    public function sqLiteUp(Schema $schema): void
    {
        //Not needed
    }

    public function sqLiteDown(Schema $schema): void
    {
        //Not needed
    }

    public function postgreSQLUp(Schema $schema): void
    {
        //Not needed
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //Not needed
    }
}
