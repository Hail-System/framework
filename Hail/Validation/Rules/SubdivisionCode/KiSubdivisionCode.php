<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Hail\Validation\Rules\SubdivisionCode;

use Hail\Validation\Rules\AbstractSearcher;

/**
 * Validator for Kiribati subdivision code.
 *
 * ISO 3166-1 alpha-2: KI
 *
 * @link http://www.geonames.org/KI/administrative-division-kiribati.html
 */
class KiSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'G', // Gilbert Islands
        'L', // Line Islands
        'P', // Phoenix Islands
    ];

    public $compareIdentical = true;
}