<?php

namespace BackBuilder\ClassContent;

use BackBuilder\ClassContent\Exception\ClassContentException;
use Symfony\Component\Security\Core\User\UserInterface,
    Symfony\Component\Security\Acl\Domain\UserSecurityIdentity,
    Symfony\Component\Security\Core\Authentication\Token\TokenInterface,
    Symfony\Component\Security\Core\Util\ClassUtils;

/**
 * Revision of a content in BackBuilder.
 * 
 * A revision is owned by a valid user and has several states :
 * 
 * * STATE_ADDED : new content, revision number to 0
 * * STATE_MODIFIED : new draft of an already revisionned content
 * * STATE_COMMITTED : one of the committed revision of a content
 * * STATE_DELETED : revision of an deleted content
 * * STATE_CONFLICTED : revision conflicted with current committed version
 * * STATE_TO_DELETE : revision to delete
 *
 * When a revision is defined as a draft of a content (ie STATE_ADDED or STATE_MODIFIED),
 * it overloads all getters and setters of its content except getUid() and setUid().
 *
 * @category    BackBuilder
 * @package     BackBuilder\ClassContent
 * @copyright   Lp digital system
 * @author      c.rouillon <rouillon.charles@gmail.com>
 * @Entity(repositoryClass="BackBuilder\ClassContent\Repository\RevisionRepository")
 * @Table(name="revision")
 * @HasLifecycleCallbacks
 */
class Revision extends AContent implements \Iterator, \Countable
{
    /**
     * Committed revision of a content
     * @var int;
     */

    const STATE_COMMITTED = 1000;

    /**
     * New content, revision number to 0
     * @var int
     */
    const STATE_ADDED = 1001;

    /**
     * New draft of an already revisionned content
     * @var int
     */
    const STATE_MODIFIED = 1002;

    /**
     * Revision conflicted with current committed version
     * @var int
     */
    const STATE_CONFLICTED = 1003;

    /**
     * Revision of an deleted content
     * @var int
     */
    const STATE_DELETED = 1004;

    /**
     * Revision to delete
     * @var int
     */
    const STATE_TO_DELETE = 1005;

    /**
     * The attached revisionned content
     * @var \BackBuilder\ClassContent\AClassContent
     * @ManyToOne(targetEntity="BackBuilder\ClassContent\AClassContent", inversedBy="_revisions", fetch="LAZY")
     * @JoinColumn(name="content_uid", referencedColumnName="uid")
     */
    private $_content;

    /**
     * The entity target content classname
     * @var string
     * @Column(type="string", name="classname")
     */
    private $_classname;

    /**
     * The owner of this revision
     * @var \Symfony\Component\Security\Acl\Domain\UserSecurityIdentity
     * @Column(type="string", name="owner")
     */
    private $_owner;

    /**
     * The comment associated to this revision
     * @var string
     * @Column(type="string", name="comment")
     */
    private $_comment;

    /**
     * Internal position in iterator
     * @var int
     */
    private $_index = 0;

    /*     * **************************************************************** */
    /*                                                                        */
    /*                        Common functions                                */
    /*                                                                        */
    /*     * **************************************************************** */

    /**
     * Class constructor.
     * @param string $uid The unique identifier of the revision
     * @param TokenInterface $token The current auth token
     */
    public function __construct($uid = null, $token = null)
    {
        parent::__construct($uid, $token);

        $this->_state = self::STATE_ADDED;
    }

    /**
     * Returns the revisionned content
     * @return \BackBuilder\ClassContent\AClassContent
     * @codeCoverageIgnore
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * Returns the entity target content classname
     * @return string
     * @codeCoverageIgnore
     */
    public function getClassname()
    {
        return $this->_classname;
    }

    /**
     * Returns the owner of the revision
     * @return \Symfony\Component\Security\Acl\Domain\UserSecurityIdentity
     * @codeCoverageIgnore
     */
    public function getOwner()
    {
        return $this->_owner;
    }

    /**
     * Returns the comment
     * @return string
     * @codeCoverageIgnore
     */
    public function getComment()
    {
        return $this->_comment;
    }

    /**
     * Sets the whole datas of the revision
     * @param array $data
     * @return \BackBuilder\ClassContent\AClassContent the current instance content
     * @codeCoverageIgnore
     */
    public function setData(array $data)
    {
        $this->_data = $data;
        return $this->_getContentInstance();
    }

    /**
     * Sets the attached revisionned content
     * @param \BackBuilder\ClassContent\AClassContent $content
     * @return \BackBuilder\ClassContent\AClassContent the current instance content
     */
    public function setContent(AClassContent $content)
    {
        $this->_content = $content;

        if (null !== $this->_content) {
            $this->setClassname(ClassUtils::getRealClass($this->_content));
        }

        return $this->_getContentInstance();
    }

    /**
     * Sets the entity target content classname
     * @param string $classname
     * @return \BackBuilder\ClassContent\AClassContent the current instance content
     * @codeCoverageIgnore
     */
    public function setClassname($classname)
    {
        $this->_classname = $classname;
        return $this->_getContentInstance();
    }

    /**
     * Sets the owner of the revision
     * @param \Symfony\Component\Security\Core\User\UserInterface $user
     * @return \BackBuilder\ClassContent\AClassContent the current instance content
     * @codeCoverageIgnore
     */
    public function setOwner(UserInterface $user)
    {
        $this->_owner = UserSecurityIdentity::fromAccount($user);
        return $this->_getContentInstance();
    }

    /**
     * Sets the comment associated to the revision
     * @param string $comment
     * @return \BackBuilder\ClassContent\AClassContent the current instance content
     * @codeCoverageIgnore
     */
    public function setComment($comment)
    {
        $this->_comment = $comment;
        return $this->_getContentInstance();
    }

    /**
     * Returns the revision content
     * @return \BackBuilder\ClassContent\AClassContent
     * @codeCoverageIgnore
     */
    protected function _getContentInstance()
    {
        return $this->getContent();
    }

    /**
     * Sets options at the construction of a new revision
     * @param mixed $options 
     * @return \BackBuilder\ClassContent\AContent
     */
    protected function _setOptions($options = null)
    {
        if ($options instanceof TokenInterface) {
            $this->_owner = UserSecurityIdentity::fromToken($options);
        }

        return $this;
    }

    /*     * ************************************************************************ */
    /*                                                                         */
    /*                       ContentSet functions                              */
    /*                                                                         */
    /*     * ************************************************************************ */

    /**
     * Empty the current set of contents
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function clear()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not clear an content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        $this->_data = array();
        $this->_index = 0;
    }

    /**
     * @see Countable::count()
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function count()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not count an content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        return count($this->_data);
    }

    /**
     * @see Iterator::current()
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function current()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not get current of a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        return $this->getData($this->_index);
    }

    /**
     * Return the first subcontent of the set
     * @return AClassContent the first element
     */
    public function first()
    {
        return $this->getData(0);
    }

    /**
     * Return the item at index
     * @param int $index
     * @return the item or NULL if $index is out of bounds
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function item($index)
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not get item of a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        if (0 <= $index && $index < $this->count())
            return $this->getData($index);

        return NULL;
    }

    /**
     * @see Iterator::key()
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function key()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not get key of a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        return $this->_index;
    }

    /**
     * Return the last subcontent of the set
     * @return AClassContent the last element
     */
    public function last()
    {
        return $this->getData($this->count() - 1);
    }

    /**
     * @see Iterator::next()
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function next()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not get next of a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        return $this->getData($this->_index++);
    }

    /**
     * Pop the content off the end of the set and return it
     * @return AClassContent Returns the last content or NULL if set is empty
     */
    public function pop()
    {
        $last = $this->last();

        if (NULL === $last)
            return NULL;

        array_pop($this->_data);
        $this->rewind();

        return $last;
    }

    /**
     * Push one element onto the end of the set
     * @param AClassContent $var The pushed values
     * @return AClassContent the current instance content
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function push(AClassContent $var)
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not push in a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        if ($this->_isAccepted($var)) {
            $this->_data[] = array(get_class($var) => $var->getUid());
        }

        return $this->getContent();
    }

    /**
     * @see Iterator::rewind()
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function rewind()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not rewind a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        $this->_index = 0;
    }

    /**
     * Shift the content off the beginning of the set and return it
     * @return AClassContent Returns the shifted content or NULL if set is empty
     */
    public function shift()
    {
        $first = $this->first();

        if (NULL === $first)
            return NULL;

        array_shift($this->_data);
        $this->rewind();

        return $first;
    }

    /**
     * Prepend one to the beginning of the set
     * @param AClassContent $var The prepended values
     * @return ContentSet The current content set
     */
    public function unshift(AClassContent $var)
    {
        if ($this->_isAccepted($var)) {
            if (!$this->_maxentry || $this->_maxentry > $this->count()) {
                array_unshift($this->_data, array($this->_getType($var) => $var->getUid()));
            }
        }

        return $this->getContent();
    }

    /**
     * @see Iterator::valid()
     * @throws ClassContentException Occurs if the attached content is not a ContentSet
     */
    public function valid()
    {
        if (!($this->_content instanceof ContentSet))
            throw new ClassContentException(sprintf('Can not valid a content %s.', get_class($this)), ClassContentException::UNKNOWN_ERROR);

        return isset($this->_data[$this->_index]);
    }

    /*     * **************************************************************** */
    /*                                                                        */
    /*                   Implementation of Serializable                       */
    /*                                                                        */
    /*     * **************************************************************** */

    /**
     * Return the serialized string of the revision
     * @return string
     */
    public function serialize()
    {
        $serialized = json_decode(parent::serialize());
        $serialized->content = (null === $this->getContent()) ? null : json_decode($this->getContent()->serialize());

        return json_encode($serialized);
    }

}