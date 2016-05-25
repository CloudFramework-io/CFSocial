<?php
namespace CloudFramework\Service\SocialNetworks\Dtos\Ecommerce;

/**
 * Class ProductDTO
 * @package CloudFramework\Service\SocialNetworks\Dtos\Ecommerce
 * @author Salvador Castro <sc@bloombees.com>
 */
class ProductDTO {
    /**
     * @var integer ID
     */
    private $id;

    /**
     * @var string The name of the product
     */
    private $title;

    /**
     * @var string The description of the product, complete with HTML formatting.
     */
    private $bodyHtml;

    /**
     * @var string The name of the vendor of the product
     */
    private $vendor;

    /**
     * @var string A categorization that a product can be tagged with, commonly used for filtering and searching
     */
    private $productType;

    /**
     * @var string A categorization that a product can be tagged with, commonly used for filtering and searching.
     *              Each comma-separated tag has a character limit of 255
     */
    private $tags;

    /**
     * @var array of ProductImageDTO
     */
    private $images = [];

    /**
     * @var array of ProductVariantDTO
     */
    private $variants = [];

    /**
     * @var array of string Custom product property names like "Size", "Color", and "Material". Products are based on
     *                      permutations of these options. A product may have a maximum of 3 options. 255 characters
     *                      limit each
     */
    private $options = [];

    /**
     * @var boolean
     */
    private $published;

    /**
     * @var string raw data returned by ecommerce platform api
     */
    private $raw;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getBodyHtml()
    {
        return $this->bodyHtml;
    }

    /**
     * @param mixed $bodyHtml
     */
    public function setBodyHtml($bodyHtml)
    {
        $this->bodyHtml = $bodyHtml;
    }

    /**
     * @return mixed
     */
    public function getVendor()
    {
        return $this->vendor;
    }

    /**
     * @param mixed $vendor
     */
    public function setVendor($vendor)
    {
        $this->vendor = $vendor;
    }

    /**
     * @return mixed
     */
    public function getProductType()
    {
        return $this->productType;
    }

    /**
     * @param mixed $productType
     */
    public function setProductType($productType)
    {
        $this->productType = $productType;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param mixed $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    /**
     * @return array
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * @param array $images
     */
    public function setImages($images)
    {
        $this->images = $images;
    }

    /**
     * @return array
     */
    public function getVariants()
    {
        return $this->variants;
    }

    /**
     * @param array $variants
     */
    public function setVariants($variants)
    {
        $this->variants = $variants;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @return mixed
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * @param mixed $published
     */
    public function setPublished($published)
    {
        $this->published = $published;
    }

    /**
     * @return string
     */
    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * @param string $raw
     */
    public function setRaw($raw)
    {
        $this->raw = $raw;
    }

    public function toArray() {
        $parameters = [];

        $parameters["title"] = $this->title;
        $parameters["body_html"] = $this->bodyHtml;
        $parameters["vendor"] = $this->vendor;
        $parameters["productType"] = $this->productType;
        $parameters["tags"] = addslashes($this->tags);

        if (count($this->images) > 0) {
            $parameters["images"] = [];
            foreach($this->images as $image) {
                if (get_class($image) === "CloudFramework\\Service\\SocialNetworks\\Dtos\\Ecommerce\\ProductImageDTO") {
                    $paramImage = [];
                    $paramImage[$image->getType()] = $image->getImage();
                    $parameters["images"][] = $paramImage;
                }
            }
        }

        if (count($this->variants) > 0) {
            $parameters["variants"] = [];
            foreach($this->variants as $variant) {
                if (get_class($variant) === "CloudFramework\\Service\\SocialNetworks\\Dtos\\Ecommerce\\ProductVariantDTO") {
                    $paramVariant = [];
                    foreach($variant->getOptions() as $key=>$option) {
                        $paramVariant["option".($key+1)] = $option;
                    }
                    $paramVariant["price"] = $variant->getPrice();
                    $paramVariant["sku"] = $variant->getSku();
                    $paramVariant["barcode"] = $variant->getBarcode();
                    $paramVariant["weight"] = $variant->getWeight();
                    $paramVariant["weight_unit"] = $variant->getWeightUnit();
                    $paramVariant["position"] = $variant->getVariantPosition();
                    $parameters["variants"][] = $paramVariant;
                }
            }
        }

        if (count($this->options) > 0) {
            $parameters["options"] = [];
            foreach($this->options as $option) {
                $paramOption = [];
                $paramOption["name"] = $option;
                $parameters["options"][] = $paramOption;
            }
        }

        $parameters["published"] = $this->published;

        return $parameters;
    }
}