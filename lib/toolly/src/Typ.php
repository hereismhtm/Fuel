<?php

namespace Toolly;

class Typ
{
    private static function lengthEduce($v)
    {
        return $v === true ? true : (settype($v, 'int') ? $v : false);
    }

    private static function lengthEduce_($v)
    {
        return $v === true ? true : (settype($v, 'int') ? '=' . $v : false);
    }

    public static function s($v = true, $check_mode = false)
    {
        if ($check_mode)
            return true;
        else
            return ['s', self::lengthEduce($v)];
    }
    public static function s_($v)
    {
        return ['s', self::lengthEduce_($v)];
    }

    public static function i($v = true, $check_mode = false)
    {
        if ($check_mode)
            return is_numeric($v) && strpos($v, '.') === false;
        else
            return ['i', self::lengthEduce($v)];
    }
    public static function i_($v)
    {
        return ['i', self::lengthEduce_($v)];
    }

    public static function d($v = true, $check_mode = false)
    {
        if ($check_mode)
            return is_numeric($v);
        else
            return ['d', self::lengthEduce($v)];
    }
    public static function d_($v)
    {
        return ['d', self::lengthEduce_($v)];
    }

    public static function dd($v = true, $check_mode = false)
    {
        if ($check_mode)
            return is_numeric($v) && strpos($v, '.') !== false;
        else
            return ['dd', self::lengthEduce($v)];
    }
    public static function dd_($v)
    {
        return ['dd', self::lengthEduce_($v)];
    }

    public static function hex($v = true, $check_mode = false)
    {
        if ($check_mode) {
            if ($v == null) $v = '';
            return preg_match('/^[0-9a-f]{1,}$/s', $v) ? true : false;
        } else
            return ['hex', self::lengthEduce($v)];
    }
    public static function hex_($v)
    {
        return ['hex', self::lengthEduce_($v)];
    }

    public static function email($v = true, $check_mode = false)
    {
        if ($check_mode)
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? true : false;
        else
            return ['email', self::lengthEduce($v)];
    }

    /**
     * Ensuring that source elements match a specific format set
     * @param array $source
     * @param array $format_set
     * @param bool $verbose
     * @return bool|string
     **/
    public static function validate(&$source, $format_set, $verbose = false)
    {
        foreach ($format_set as $key => $value) {
            $element = null;
            $type = 's';
            $length = true;

            if (is_numeric($key)) {
                $element = $value;
            } else {
                $element = $key;
                if (is_array($value)) {
                    $type = $value[0];
                    $length = $value[1];
                } else {
                    $type = $value;
                }
            }

            $element_set = $element . ': ' . $type . '(' . ($length === true ? '' : $length) . ')';

            if (!isset($source[$element])) return $verbose ? $element_set . ' -missing-' : false;

            if ($source[$element] === '') return $verbose ? $element_set . ' -void-' : false;

            if (strpos($length, '=') === 0) {
                $length = strlen($source[$element]) == intval(str_replace('=', '', $length));
            } else {
                $length = strlen($source[$element]) <= $length;
            }
            if (!$length) return $verbose ? $element_set . ' -length-' : false;

            if (trim($source[$element]) === '') return $verbose ? $element_set . ' -nothing-' : false;
            if (!Typ::$type($source[$element], true)) return $verbose ? $element_set . ' -type-' : false;
        }

        return true;
    }
}
