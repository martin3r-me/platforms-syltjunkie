<?php

namespace Platform\Syltjunkie\Contracts;

use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Models\SjChannelPost;

interface ChannelPublisherContract
{
    /**
     * @return array{success: bool, external_post_id: ?string, error: ?string}
     */
    public function publish(SjChannel $channel, SjChannelPost $post): array;
}
