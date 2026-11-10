<?php

namespace Schemastud\Frame\Vocabulary;

/**
 * What {@see WidgetVocabulary} found at the configured path. A missing or unreadable file is a STATE the reader
 * reports, never an exception (ticket 05, ruling 1): the doctor turns it into an inconclusive finding that names the
 * command.
 */
enum VocabularyState: string
{
    /** The file parsed as a JSON object. Whether its version is one a reader knows is the caller's question. */
    case Readable = 'readable';

    /** No file at the path. */
    case Missing = 'missing';

    /** A file is there but could not be read, or is not a JSON object. {@see WidgetVocabulary::$error} says why. */
    case Unreadable = 'unreadable';
}
