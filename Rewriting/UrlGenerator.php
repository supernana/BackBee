<?php

namespace BackBuilder\Rewriting;

use BackBuilder\BBApplication,
    BackBuilder\NestedNode\Page,
    BackBuilder\ClassContent\AClassContent,
    BackBuilder\Util\String,
    BackBuilder\Rewriting\Exception\RewritingException;

/**
 * Utility class to generate page URL according config rules
 * 
 * Available options are:
 *    * preserve-online  : if true, forbid the URL updating for online page
 *    * preserve-unicity : if true check for unique computed URL
 * 
 * Available rules are:
 *    * _root_      : scheme for root node
 *    * _default_   : default scheme
 *    * _content_   : array of schemes indexed by content classname
 * 
 * Available params are:
 *    * $parent     : page parent url
 *    * $uid        : page uid
 *    * $title      : the urlized form of the title
 *    * $date       : the creation date formated to YYYYMMDD
 *    * $datetime   : the creation date formated to YYYYMMDDHHII
 *    * $time       : the creation date formated to HHIISS
 *    * $content->x : the urlized form of the 'x' property of content
 *
 * @category    BackBuilder
 * @package     BackBuilder\Rewriting
 * @copyright   Lp system
 * @author      c.rouillon
 */
class UrlGenerator implements IUrlGenerator
{

    /**
     * Current BackBuilder application
     * @var BackBuilder\BBApplication
     */
    private $_application;

    /**
     * if true, forbid the URL updating for online page
     * @var boolean 
     */
    private $_preserveOnline = true;

    /**
     * if true, check for unique computed URL
     * @var boolean
     */
    private $_preserveUnicity = true;

    /**
     * Available rewriting schemes
     * @var array 
     */
    private $_schemes = array();

    /**
     * Array of class content used by one of the schemes
     * @var array
     */
    private $_descriminators;

    /**
     * Class constructor
     * @param \BackBuilder\BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->_application = $application;

        if (NULL !== $rewritingConfig = $this->_application->getConfig()->getRewritingConfig()) {
            if (true === array_key_exists('preserve-online', $rewritingConfig)) {
                $this->_preserveOnline = (true === $rewritingConfig['preserve-online']);
            }

            if (true === array_key_exists('preserve-unicity', $rewritingConfig)) {
                $this->_preserveUnicity = (true === $rewritingConfig['preserve-unicity']);
            }

            if (true === array_key_exists('scheme', $rewritingConfig) && true === is_array($rewritingConfig['scheme'])) {
                $this->_schemes = $rewritingConfig['scheme'];
            }
        }
    }

    /**
     * Returns the list of class content names used by one of schemes
     * Dynamically add a listener on descrimator.onflush event to RewritingListener
     * @return array
     */
    public function getDescriminators()
    {
        if (NULL === $this->_descriminators) {
            $this->_descriminators = array();

            if (true === array_key_exists('_content_', $this->_schemes)) {
                foreach (array_keys($this->_schemes['_content_']) as $descriminator) {
                    $this->_descriminators[] = 'BackBuilder\ClassContent\\' . $descriminator;

                    if (NULL !== $this->_application->getEventDispatcher()) {
                        $this->_application
                                ->getEventDispatcher()
                                ->addListener(str_replace(NAMESPACE_SEPARATOR, '.', $descriminator) . '.onflush', array('BackBuilder\Event\Listener\RewritingListener', 'onFlushContent'));
                    }
                }
            }
        }

        return $this->_descriminators;
    }

    /**
     * Returns the URL of the page
     * @param \BackBuilder\NestedNode\Page $page               The page
     * @param \BackBuilder\ClassContent\AClassContent $content The optionnal main content of the page
     * @return string The URL                                  The generated URL
     */
    public function generate(Page $page, AClassContent $content = NULL, $exceptionOnMissingScheme = true)
    {
        if ($this->_preserveOnline && ($page->getState() & Page::STATE_ONLINE)) {
            return $page->getUrl();
        }

        if ($page->isRoot() && true == array_key_exists('_root_', $this->_schemes)) {
            return $this->_generate($this->_schemes['_root_'], $page, $content);
        }

        if (NULL !== $content && true === array_key_exists('_content_', $this->_schemes)) {
            $shortClassname = str_replace('BackBuilder\ClassContent\\', '', get_class($content));
            if (true === array_key_exists($shortClassname, $this->_schemes['_content_'])) {
                return $this->_generate($this->_schemes['_content_'][$shortClassname], $page, $content);
            }
        }

        if (true == array_key_exists('_default_', $this->_schemes)) {
            return $this->_generate($this->_schemes['_default_'], $page, $content);
        }

        if (true === $exceptionOnMissingScheme) {
            throw new RewritingException(sprintf('None rewriting scheme found for Page(%s)', $page->getUid()), RewritingException::MISSING_SCHEME);
        }

        return '/' . $page->getUid();
    }

    /**
     * Computes the URL of a page according to a scheme
     * @param array $scheme                                    The scheme to apply
     * @param \BackBuilder\NestedNode\Page $page               The page
     * @param \BackBuilder\ClassContent\AClassContent $content The optionnal main content of the page
     * @return string                                          The generated URL
     */
    private function _generate($scheme, Page $page, AClassContent $content = NULL)
    {
        $replacement = array(
            '$parent' => ($page->isRoot()) ? '' : $page->getParent()->getUrl(),
            '$title' => String::urlize($page->getTitle()),
            '$date' => $page->getCreated()->format('ymd'),
            '$datetime' => $page->getCreated()->format('ymdHi'),
            '$time' => $page->getCreated()->format('His'),
        );

        $matches = array();
        if (preg_match_all('/(\$content->[a-z]+)/i', $scheme, $matches)) {
            foreach ($matches[1] as $pattern) {
                try {
                    $replacement[$pattern] = eval('return \BackBuilder\Util\String::urlize(' . $pattern . ');');
                } catch (\Exception $e) {
                    $replacement[$pattern] = '';
                }
            }
        }

        $url = preg_replace('/\/+/', '/', str_replace(array_keys($replacement), array_values($replacement), $scheme));
        if (true === $this->_preserveUnicity) {
            $this->_checkUnicity($page, $url);
        }

        return $url;
    }

    /**
     * Checks for the unicity of the URL and postfixe it if need
     * @param \BackBuilder\NestedNode\Page $page   The page
     * @param string &$url                         The reference of the generated URL
     */
    private function _checkUnicity(Page $page, &$url)
    {
        $baseurl = $url;
        $pageRepository = $this->_application->getEntityManager()->getRepository('BackBuilder\NestedNode\Page');

        $count = 1;
        $existings = $pageRepository->findBy(array('_url' => $url, '_root' => $page->getRoot()));
        foreach ($existings as $existing) {
            if (!$existing->isDeleted() && $existing->getUid() != $page->getUid()) {
                $url = $baseurl . '-' . ($count++);
            }
        }
    }

}