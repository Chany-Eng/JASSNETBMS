<?php
/**
 * ValidationHelper - Common validation functions
 */

namespace App\Helpers;

class ValidationHelper
{
    /**
     * Validate email
     *
     * @param string $email
     * @return bool
     */
    public static function email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL
     *
     * @param string $url
     * @return bool
     */
    public static function url($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate phone number
     *
     * @param string $phone
     * @return bool
     */
    public static function phone($phone)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return array|true Returns error array or true if valid
     */
    public static function password($password)
    {
        $errors = [];

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain special character';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Validate numeric value
     *
     * @param mixed $value
     * @param float|null $min
     * @param float|null $max
     * @return bool
     */
    public static function numeric($value, $min = null, $max = null)
    {
        if (!is_numeric($value)) {
            return false;
        }

        if ($min !== null && $value < $min) {
            return false;
        }

        if ($max !== null && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate integer value
     *
     * @param mixed $value
     * @param int|null $min
     * @param int|null $max
     * @return bool
     */
    public static function integer($value, $min = null, $max = null)
    {
        if (!is_numeric($value) || intval($value) != $value) {
            return false;
        }

        $value = intval($value);

        if ($min !== null && $value < $min) {
            return false;
        }

        if ($max !== null && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate string length
     *
     * @param string $string
     * @param int|null $min
     * @param int|null $max
     * @return bool
     */
    public static function stringLength($string, $min = null, $max = null)
    {
        $length = strlen($string);

        if ($min !== null && $length < $min) {
            return false;
        }

        if ($max !== null && $length > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate date format
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    public static function dateFormat($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate array has required keys
     *
     * @param array $array
     * @param array $requiredKeys
     * @return bool
     */
    public static function arrayHasKeys($array, $requiredKeys)
    {
        foreach ($requiredKeys as $key) {
            if (!isset($array[$key]) || $array[$key] === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate alphanumeric string
     *
     * @param string $string
     * @param bool $allowSpaces
     * @return bool
     */
    public static function alphanumeric($string, $allowSpaces = false)
    {
        $pattern = $allowSpaces ? '/^[a-zA-Z0-9\s]+$/' : '/^[a-zA-Z0-9]+$/';
        return preg_match($pattern, $string) === 1;
    }

    /**
     * Validate username
     *
     * @param string $username
     * @return bool
     */
    public static function username($username)
    {
        return preg_match('/^[a-zA-Z0-9_.-]+$/', $username) === 1 && 
               strlen($username) >= 3 && 
               strlen($username) <= 20;
    }

    /**
     * Validate latitude/longitude
     *
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public static function coordinates($latitude, $longitude)
    {
        return is_numeric($latitude) && is_numeric($longitude) &&
               $latitude >= -90 && $latitude <= 90 &&
               $longitude >= -180 && $longitude <= 180;
    }
}
