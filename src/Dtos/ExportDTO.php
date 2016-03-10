<?php
namespace CloudFramework\Service\SocialNetworks\Dtos;

/**
 * Class ExportDTO
 * @package CloudFramework\Service\SocialNetworks\Connectors
 * @author Salvador Castro <sc@bloombees.com>
 */
class ExportDTO {
    private $published;
    private $title;
    private $urlObject;
    private $idUser;
    private $nameUser;
    private $urlUser;

    public function __construct($published = null, $title = null, $urlObject = null,
                                    $idUser = null, $nameUser = null, $urlUser = null) {
        $this->setPublished($published);
        $this->setTitle($title);
        $this->setUrlObject($urlObject);
        $this->setIdUser($idUser);
        $this->setNameUser($nameUser);
        $this->setUrlUser($urlUser);
    }

    /**
     * @return string
     */
    public function getPublished() {
        return $this->published;
    }

    public function setPublished($published) {
        $this->published = $published;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getUrlObject() {
        return $this->urlObject;
    }

    public function setUrlObject($urlObject) {
        $this->urlObject = $urlObject;
    }

    /**
     * @return string
     */
    public function getIdUser() {
        return $this->idUser;
    }

    public function setIdUser($idUser) {
        $this->idUser = $idUser;
    }

    /**
     * @return string
     */
    public function getNameUser() {
        return $this->nameUser;
    }

    public function setNameUser($nameUser) {
        $this->nameUser = $nameUser;
    }

    /**
     * @return string
     */
    public function getUrlUser() {
        return $this->urlUser;
    }

    public function setUrlUser($urlUser) {
        $this->urlUser = $urlUser;
    }
}