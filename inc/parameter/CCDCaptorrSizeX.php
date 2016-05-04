<?php
/**
 * CCDCaptorSizeX
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * Class CCDCaptorSizeX
 *
 * A NumericalParameter to represent the x-size of the CCD pixel.
 *
 * @package hrm\param
 */
class CCDCaptorSizeX extends NumericalParameter
{

    /**
     * CCDCaptorSizeX constructor.
     */
    public function __construct()
    {
        parent::__construct("CCDCaptorSizeX");
    }

    /**
     * Confirms that this is a Capture Parameter.
     * @return bool Always true.
     */
    public function isForCapture()
    {
        return True;
    }

    /**
     * Returns the string representation of the CCDCaptorSizeX Parameter.
     * @return string String representation of the CCDCaptorSizeX Parameter.
     */
    public function displayString()
    {
        $result = $this->formattedName('pixel size');
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $this->value . "\n";
        }
        return $result;
    }
}
