<?php

namespace BackBuilder\Services\Less;

class LessParser {
    
    public static $DISABLE_FILEDS = array("import 'grid_constant.less'", 'gridColumns');
    
    private $filename;
    private $pattern;
    private $matches;
    private $matchesComm;
    private $data;
    
    private function toLessAttr($attribute, $val)
    {
        return "@".str_pad($attribute.":", 30, " ", STR_PAD_RIGHT).$val.";\n";
    }
    
    private function parse()
    {
        $content = file($this->filename);
        foreach ($content as $line => $string)
            $this->parseLine($string, $line);
        return $this->matchesComm;
    }
    
    private function getAttrKey($array, $needle)
    {
       foreach ($array as $keyItem => $val)
       {
           if ($val['name'] == $needle)
               return $keyItem;
       }
       return null;
    }
    
    private function parseLine($buffer, $numline)
    {
        $matches    = array();
        $matches2   = array();
        
        preg_match_all($this->pattern["variables"], $buffer, $matches);
        preg_match_all($this->pattern["comments"], $buffer, $matches2);
        
        if (isset($matches2[1][0]))
        {
            $this->matchesComm[] = array('headerComm' => $matches2[1][0]);
            $this->matches = array();
        }
        
        if (isset($matches[1]) && $matches[1] != array())
        {
            $attribute                      = trim(ltrim($matches[1][0]));
            $value                          = trim(ltrim($matches[2][0]));
            $editable                       = (strpos($value, '@') !== false || in_array($attribute, self::$DISABLE_FILEDS)) ? false: true;
            $widget                         = (substr($value, 0, 1) == '#') ? 'color': ((preg_match("/.*[\d]{2}px;$/", $value) > 0) ? 'default': 'font');
            
            $this->matches[$attribute]      = array('value' => $value, 'widget' => $widget, 'editable' => $editable);
            if (count($this->matchesComm) != 0)
                $this->matchesComm[count($this->matchesComm) - 1]['attributes'] = $this->matches;
            else
                $this->matchesComm[] = array('headerComm' => "", 'attributes' => $this->matches);
        }
    }
    
    public function __construct($filename = null)
    {
        $this->data         = "";
        $this->matches      = array();
        $this->matchesComm  = array();
        $this->pattern      = array("variables" => "/^[@]([^:]+)[:]([^;]+)/" /* variable and value ex: @attribu: value; */, "comments" => "/[\/\/]{2}[\s][#]([^\s]+)/" /* header category ex: // #title */);
        
        if ($filename !== null && file_exists($filename))
        {
            $this->filename = $filename;
            $this->parse();
        }
        elseif ($filename !== null)
            throw new \Exception('LessParser: file "'.$filename.'" can\'t be load');
        else
            $this->filename = "";
    }
    
    public function loadData($data = array())
    {   
        $buffer     = "";
        $content    = file($this->filename);
        foreach ($content as $line)
        {
            $result = preg_match_all($this->pattern["variables"], $line, $matches);
            if (isset($matches[1]) && $matches[1] != array())
            {
                $attribute = trim(ltrim($matches[1][0]));
                $value     = trim(ltrim($matches[2][0]));
                $editable  = (strpos($value, '@') !== false || in_array($attribute, self::$DISABLE_FILEDS)) ? false: true;
                $widget    = (substr($value, 0, 1) == '#') ? 'color': ((preg_match("/.*[\d]{2}px;$/", $value) > 0) ? 'default': 'font');
                
                if (NULL !== ($attrKey = $this->getAttrKey($data, $attribute)))
                    $buffer .= $this->toLessAttr($attribute, $data[$attrKey]['value']);
                else
                    $buffer .= $line;
            }
            else
                $buffer .= $line;
        }
        $this->data = $buffer;
    }
    
    public function getLessVariables()
    {
        return $this->matchesComm;
    }
    
    public function save($filename = null)
    {
        if ($filename === null)
            $handle = fopen($this->filename, 'w');
        else
            $handle = fopen($filename, 'w');
        fwrite($handle, $this->data);
        fclose($handle);
    }
    
    public function addHeader($header)
    {
        $this->matchesComm[] = array('headerComm' => $header, 'attributes' => array());
        return $this;
    }
    
    public function addAttribute($attr, $value)
    {
        $this->matchesComm[count($this->matchesComm) - 1]['attributes'][] = array($attr => $value);
        return $this;
    }
}