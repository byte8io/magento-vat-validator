<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationLogSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class ValidationLogSearchResults extends SearchResults implements ValidationLogSearchResultsInterface
{
}
