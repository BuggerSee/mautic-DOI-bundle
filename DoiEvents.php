<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle;

final class DoiEvents
{
    /**
     * Dispatched when verification is skipped and a custom post-action should be executed.
     */
    public const DOI_ON_SKIP_POST_ACTION = 'leuchtfeuer.doi.on_skip_post_action';
}
