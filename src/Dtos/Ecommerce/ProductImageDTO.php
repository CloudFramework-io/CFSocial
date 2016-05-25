<?php
namespace CloudFramework\Service\SocialNetworks\Dtos\Ecommerce;

/**
 * Class ProductImageDTO
 * @package CloudFramework\Service\SocialNetworks\Dtos\Ecommerce
 * @author Salvador Castro <sc@bloombees.com>
 */
class ProductImageDTO {
    /**
     * @var string The type of the image: "src" if it's an url, "attachment" if it's a base64 encoded image
     */
    private $type;

    /**
     * @var string Url or base64 of the image.
     */
    private $image;

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param string $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }


}