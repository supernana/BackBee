<?php

namespace BackBuilder\Util;

class String
{

    /**
     * Return a mixed array of options according the defaults values provided
     *
     * @access private
     * @param array $options
     * @param array $default
     * @return array The final options
     */
    private static function _getOptions($options, $default)
    {
        if (is_array($options)) {
            foreach ($default as $key => $value) {
                if (isset($options[$key]))
                    $default[$key] = $options[$key];
            }
        }

        return $default;
    }

    /**
     * Convert a string to ASCII
     *
     * @access public
     * @param string $str The string to convert
     * @param string $charset The default charset to use
     * @return string The converted string
     */
    public static function toASCII($str, $charset = 'UTF-8')
    {
        $asciistr = '';

        if (mb_detect_encoding($str, 'UTF-8', true) === FALSE) {
            $str = utf8_encode($str);
        }

        iconv_set_encoding('input_encoding', 'UTF-8');
        iconv_set_encoding('internal_encoding', 'UTF-8');
        iconv_set_encoding('output_encoding', $charset);

        $str = html_entity_decode($str, ENT_QUOTES, $charset);

        $strlen = iconv_strlen($str, $charset);
        for ($i = 0; $i < $strlen; $i++) {
            $char = iconv_substr($str, $i, 1, $charset);
            if (!preg_match('/[`\'^~"]+/', $char)) {
                if ('UTF-8' == $charset)
                    $asciistr .= preg_replace('/[`\'^~"]+/', '', iconv($charset, 'ASCII//TRANSLIT//IGNORE', $char));
                else
                    $asciistr .= preg_replace('/[`\'^~"]+/', '', iconv('UTF-8', $charset . '//TRANSLIT//IGNORE', $char));
            } else {
                $asciistr .= $char;
            }
        }

        return $asciistr;
    }

    /**
     * Convert string to utf-8 encoding if need
     * @param string $str
     * @return string
     */
    public static function toUTF8($str)
    {
        if (NULL !== $str && FALSE === mb_detect_encoding($str, 'UTF-8', true)) {
            $str = utf8_encode($str);
        }

        return $str;
    }

    /**
     * Normalize a string to a valid path file name
     *
     * @access public
     * @param string $str The string to normalize
     * @param array $options Convert options to use :
     *                         - extension		string The extension to concat to the converted string
     *                         - spacereplace	string The character replacement for space characters
     *                         - lengthlimit	int The max length of the returned string
     * @param string $charset The default charset to use
     * @return string The converted string
     */
    public static function toPath($str, $options = NULL, $charset = 'UTF-8')
    {
        $options = self::_getOptions($options, array(
                    'extension' => '',
                    'spacereplace' => NULL,
                    'lengthlimit' => 250
                ));

        $str = trim(preg_replace('/(?:[^\w\-\.~\+% ]+|%(?![A-Fa-f0-9]{2}))/', '', self::toASCII($str, $charset)));
        $str = preg_replace('/\s+/', NULL === $options['spacereplace'] ? '' : $options['spacereplace'], $str);

        return substr($str, 0, $options['lengthlimit']) . $options['extension'];
    }

    /**
     * Normalize a string to a valid url path
     *
     * @access public
     * @param string $str The string to normalize
     * @param array $options Convert options to use :
     *                         - extension		string The extension to concat to the converted string
     *                         - separators		string A pattern of space characters to replace
     *                         - spacereplace	string The character replacement for space characters
     *                         - lengthlimit	int The max length of the returned string
     * @param string $charset The default charset to use
     * @return string The converted string
     */
    public static function urlize($str, $options = NULL, $charset = 'UTF-8')
    {
        $options = self::_getOptions($options, array(
                    'extension' => '',
                    'separators' => '/[.\'’ ]+/',
                    'spacereplace' => '-',
                    'lengthlimit' => 250
                ));

        $str = str_replace(array('%', '€', '“', '”', '…'), array('percent', 'euro', '"', '"', '...'), $str);
        $str = preg_replace($options['separators'], ' ', $str);
        return strtolower(preg_replace('/[-]+/', '-', self::toPath($str, $options, $charset)));
    }

    /**
     * Return an XML compliant form of string
     * @param string $str
     * @param Boolean $striptags Are HTML tags to be striped from string
     * @return string The XML compliant string
     */
    public static function toXmlCompliant($str, $striptags = true)
    {
        if (true === $striptags) {
            $str = strip_tags($str);
        }

        $str = html_entity_decode($str, ENT_COMPAT, 'UTF-8');
        $str = str_replace(array('<', '>', '&'), array('&lt;', '&gt;', '&amp;'), $str);

        return $str;
    }

    /**
     * truncate a string
     * @param int $start
     * @param int length
     * @param string ellipsis string
     * @return string
     * copied from symfony 1.2 
     */
    public static function truncateText($text, $length = 30, $truncate_string = '...', $truncate_lastspace = false)
    {
        if ($text == '') {
            return '';
        }

        $mbstring = extension_loaded('mbstring');
        if ($mbstring) {
            $old_encoding = mb_internal_encoding();
            @mb_internal_encoding(mb_detect_encoding($text));
        }
        $strlen = ($mbstring) ? 'mb_strlen' : 'strlen';
        $substr = ($mbstring) ? 'mb_substr' : 'substr';

        if ($strlen($text) > $length) {
            $truncate_text = $substr($text, 0, $length - $strlen($truncate_string));
            if ($truncate_lastspace) {
                $truncate_text = preg_replace('/\s+?(\S+)?$/', '', $truncate_text);
            }
            $text = $truncate_text . $truncate_string;
        }

        if ($mbstring) {
            @mb_internal_encoding($old_encoding);
        }

        return $text;
    }

}