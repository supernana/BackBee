<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Less
 *
 * @author Nicolas BREMONT<nicolas.bremont@group-lp.com>
 */

namespace BackBuilder\Services\Local;

use BackBuilder\Services\Less\LessParser;
use BackBuilder\Services\Less\LessTheme;
use BackBuilder\Services\Local\AbstractServiceLocal;
use BackBuilder\Services\Gabarit\RenderBackgroud;
use BackBuilder\Exception\BBException;
use BackBuilder\BBApplication;


class Less extends AbstractServiceLocal {
    
    private $lessPathRoot;
    private $lessPathTheme;
    
    public function __construct(BBApplication $bbapp = null)
    {
        parent::__construct($bbapp);
        
        if (NULL !== $bbapp) {
            $this->lessPathRoot = $bbapp->getCurrentResourceDir().DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.'less';
            $this->lessPathTheme = $bbapp->getCurrentResourceDir().DIRECTORY_SEPARATOR.'themes';
        }
    }
    
    
    private function getLessParserObject($filename = "")
    {
        if (file_exists($filename))
        {
            $lessParser = new LessParser($filename);
            return $lessParser;
        }
        else
            throw new \Exception("file: ".$filename." not found !");
    }
    
    /**
     * @exposed(secured=true)
     */
    public function sendLessVariables($params)
    {
        $lessParser = $this->getLessParserObject($this->lessPathRoot.DIRECTORY_SEPARATOR."variables.less");
        $lessParser->loadData($params);
        $lessParser->save();
        return "variable saved";
    }
    
    /**
     * @exposed(secured=true)
     */
    public function sendLessGridConstant($gridColumnWidth, $gridGutterWidth)
    {
        $lessConfig = $this->bbapp->getConfig()->getSection('less');
        
        if (isset($lessConfig['dirname']) && isset($lessConfig['gridconstant']))
            $filename = $this->bbapp->getCurrentResourceDir().DIRECTORY_SEPARATOR.$lessConfig['dirname'].DIRECTORY_SEPARATOR.$lessConfig['gridconstant'];
        else
            throw new BBException("Please check config file: dirname and gridconstant value");
        
        if (FALSE === ($handler = @fopen($filename, 'w')))
        {
            if (!is_writable($filename))
                throw new \Exception("File is not writable!");
        }
        
        $gridColumns            = $lessConfig['gridcolumn'];
        $total                  = ($gridColumns * $gridColumnWidth) + ($gridGutterWidth * ($gridColumns - 1));
        $fluidGridColumnWidth   = (($gridColumnWidth * 100) / $total);
        $fluidGridGutterWidth   = (($gridGutterWidth * 100) / $total);
        $string                 = "@gridColumnWidth:\t\t".$gridColumnWidth."px;\n@gridGutterWidth:\t\t".$gridGutterWidth."px;\n\n@fluidGridColumnWidth:\t\t".$fluidGridColumnWidth."%;\n@fluidGridGutterWidth:\t\t".$fluidGridGutterWidth."%;";

        fwrite($handler, $string);
        fclose($handler);
        
        // generate background image
        $pathToDest = $this->bbapp->getCurrentResourceDir() . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "grid.png";
        $renderBackground = new RenderBackgroud($gridColumns, $gridColumnWidth, $gridGutterWidth, $pathToDest);
        
        return "variable saved";
    }
    
    /**
     * @exposed(secured=true)
     */
    public function getLessVariables($params = null)
    {
        $lessParser = $this->getLessParserObject($this->lessPathRoot.DIRECTORY_SEPARATOR."variables.less");
        return $lessParser->getLessVariables();
    }
    
    /**
     * @exposed(secured=true)
     */
    public function getLessVariablesBB4($theme = null)
    {
        if ($theme !== null)
            $lessParser = $this->getLessParserObject($this->lessPathTheme.DIRECTORY_SEPARATOR.$theme.DIRECTORY_SEPARATOR.'less'.DIRECTORY_SEPARATOR."admin-variables.less");
        else if (null !== $this->bbapp->getTheme()->getIncludePath('less_dir')) {
            $lessfile = 'admin-variables.less';
            \BackBuilder\Util\File::resolveFilepath($lessfile, null, array('include_path' => $this->bbapp->getTheme()->getIncludePath('less_dir')));
           $lessParser = $this->getLessParserObject($lessfile);
        } else
            $lessParser = $this->getLessParserObject($this->lessPathRoot.DIRECTORY_SEPARATOR."admin-variables.less");
        
        return $lessParser->getLessVariables();
    }
    
    /**
     * @exposed(secured=true)
     */
    public function sendLessVariablesBB4($params, $theme = null)
    {
        if ($theme != null)
            $lessParser = $this->getLessParserObject($this->lessPathTheme.DIRECTORY_SEPARATOR.$theme.DIRECTORY_SEPARATOR.'less'.DIRECTORY_SEPARATOR."admin-variables.less");
        else if (null !== $this->bbapp->getTheme()->getIncludePath('less_dir')) {
            $lessfile = 'admin-variables.less';
            \BackBuilder\Util\File::resolveFilepath($lessfile, null, array('include_path' => $this->bbapp->getTheme()->getIncludePath('less_dir')));
           $lessParser = $this->getLessParserObject($lessfile);
        } else
            $lessParser = $this->getLessParserObject($this->lessPathRoot.DIRECTORY_SEPARATOR."admin-variables.less");
        
        $lessParser->loadData($params);
        $lessParser->save();
        return "variable saved";
    }
    
    /**
     * @exposed(secured=true)
     */
    public function getGridConstant()
    {
        if (null !== $this->bbapp->getTheme()->getIncludePath('less_dir')) {
            $lessfile = 'grid_constant.less';
            \BackBuilder\Util\File::resolveFilepath($lessfile, null, array('include_path' => $this->bbapp->getTheme()->getIncludePath('less_dir')));
           $lessParser = $this->getLessParserObject($lessfile);
        } else
            $lessParser = $this->getLessParserObject($this->lessPathRoot.DIRECTORY_SEPARATOR."grid_constant.less");
        
        return $lessParser->getLessVariables();
    }
    
    /**
     * @exposed(secured=true)
     */
    public function getGridColumns()
    {
        $lessConfig = $this->bbapp->getConfig()->getLessConfig();
        return $lessConfig['gridcolumn'];
    }
    
    /**
     * @exposed(secured=true)
     */
    public function changeTheme($theme = "")
    {
        if ($theme == "")
            return "please select theme";
        
        return LessTheme::getInstance($this->bbapp)->loadTheme($theme);
    }
    
    /**
     * @exposed(secured=true)
     */
    public function getThemes()
    {
        return LessTheme::getInstance($this->bbapp)->getAllThemes();
    }
    
    /**
     * @exposed(secured=true)
     */
    public function generateNewTheme($theme)
    {
        return LessTheme::getInstance($this->bbapp)->createNewTheme($theme);
    }
    
    /**
     * @exposed(secured=true)
     */
    public function generateStyle($theme)
    {
        return LessTheme::getInstance($this->bbapp)->generateStyle($theme);
    }
    
    /**
     * @exposed(secured=true)
     */
    public function getLessFonts()
    {
        $cfgLess    = $this->bbapp->getConfig()->getSection('less');
        $fonts      = $cfgLess['fonts'];
        return $fonts;
    }
}

?>