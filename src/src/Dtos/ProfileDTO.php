<?php
namespace CloudFramework\Service\SocialNetworks\Dtos;

/**
 * Class ProfileDTO
 * @package CloudFramework\Service\SocialNetworks\Connectors
 * @author Salvador Castro <sc@bloombees.com>
 */
class ProfileDTO {
    private $idUser;
    private $fullName;
    private $email;
    private $imageUrl;

    public function __construct($idUser = null, $fullName = null, $email = null, $imageUrl = null) {
        $this->setIdUser($idUser);
        $this->setFullName($fullName);
        $this->setEmail($email);
        $this->setImageUrl($imageUrl);
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
    public function getFullName() {
        return $this->fullName;
    }

    public function setFullName($fullName) {
        $this->fullName = $fullName;
    }

    /**
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getImageUrl() {
        return $this->imageUrl;
    }

    public function setImageUrl($imageUrl) {
        $this->imageUrl = $imageUrl;
    }
}