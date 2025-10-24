<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle;

final class DoiEvents
{
    /**
     * Dispatched when verification is skipped and a custom post-action should be executed.
     */
    public const DOI_ON_SKIP_POST_ACTION = 'leuchtfeuer.doi.on_skip_post_action';

    /**
     * Dispatched when a response is set for the skip post-action process.
     */
    public const DOI_ON_SET_SKIP_POST_ACTION_RESPONSE = 'leuchtfeuer.doi.on_set_skip_post_action_response';
}
