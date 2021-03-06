<?php

namespace JLaso\TranslationsBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * File
 *
 * @MongoDB\EmbeddedDocument
 *
 */
class File
{
    /**
     *
     * @MongoDB\String
     */
    protected $locale;

    /**
     * @MongoDB\String
     */
    protected $message;

    /**
     * @MongoDB\String
     */
    protected $fileName;

    /**
     * @MongoDB\Timestamp
     */
    protected $createdAt;

    /**
     * @MongoDB\Timestamp
     */
    protected $updatedAt;

    /**
     * @MongoDB\Boolean
     */
    protected $deleted;

    /**
     * @MongoDB\Boolean
     */
    protected $approved;

    public function __construct()
    {
        $this->locale    = null;
        $this->message   = null;
        $this->createdAt = new \MongoTimestamp();
        $this->updatedAt = new \MongoTimestamp();
        $this->fileName  = null;
        $this->deleted   = false;
        $this->approved  = false;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $deleted
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    /**
     * @return mixed
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * @param mixed $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param mixed $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @param mixed $approved
     */
    public function setApproved($approved)
    {
        $this->approved = $approved;
    }

    /**
     * @return mixed
     */
    public function getApproved()
    {
        return $this->approved;
    }
}
