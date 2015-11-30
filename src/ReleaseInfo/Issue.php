<?php
namespace TheRat\SymDep\ReleaseInfo;

class Issue
{
    protected $name;

    protected $assignee;

    protected $link;

    protected $status;

    protected $title;

    public function __construct(
        $name,
        $title,
        $link,
        $assignee,
        $status
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->link = $link;
        $this->assignee = $assignee;
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getAssignee()
    {
        return $this->assignee;
    }

    /**
     * @return mixed
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }
}
