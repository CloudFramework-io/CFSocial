<?php
namespace CloudFramework\Service\SocialNetworks\Dtos\Ecommerce;

/**
 * Class ProductVariantDTO
 * @package CloudFramework\Service\SocialNetworks\Dtos\Ecommerce
 * @author Salvador Castro <sc@bloombees.com>
 */
class ProductVariantDTO {
    /**
     * @var array Options (option1, option2, option3, ...)
     */
    private $options;

    /**
     * @var float Price of the variant
     */
    private $price;

    /**
     * @var string SKU of the variant
     */
    private $sku;

    /**
     * @var string Barcode of the variant
     */
    private $barcode;

    /**
     * @var float Weight of the variant
     */
    private $weight;

    /**
     * @var string Weight unit of the variant:  "g", "kg, "oz", or "lb"
     */
    private $weightUnit;

    /**
     * @var string The order of the product variant.
     */
    private $variantPosition;

    /**
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
    }

    /**
     * @return string
     */
    public function getBarcode()
    {
        return $this->barcode;
    }

    /**
     * @param string $barcode
     */
    public function setBarcode($barcode)
    {
        $this->barcode = $barcode;
    }

    /**
     * @return float
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param float $weight
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    /**
     * @return float
     */
    public function getWeightUnit()
    {
        return $this->weightUnit;
    }

    /**
     * @param float $weightUnit
     */
    public function setWeightUnit($weightUnit)
    {
        $this->weightUnit = $weightUnit;
    }

    /**
     * @return string
     */
    public function getVariantPosition()
    {
        return $this->variantPosition;
    }

    /**
     * @param string $position
     */
    public function setVariantPosition($position)
    {
        $this->variantPosition = $position;
    }
}