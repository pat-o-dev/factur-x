<?php

declare(strict_types=1);

namespace PatODev\FacturX\Exception;

/**
 * Thrown when a PDF is fully parseable but its Catalog declares no
 * /Names /EmbeddedFiles or /AF entry at all — i.e. it is a normal PDF,
 * not a Factur-X hybrid. See Pdf\EmbeddedXmlExtractor.
 */
class NoEmbeddedXmlException extends FacturXException
{
}
