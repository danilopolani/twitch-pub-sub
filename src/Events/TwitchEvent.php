<?php

namespace Danilopolani\TwitchPubSub\Events;

use Illuminate\Foundation\Events\Dispatchable;

abstract class TwitchEvent
{
  use Dispatchable;

  public array $data;

  /**
  * Create a new event instance.
  *
  * @param  array $data
  * @return void
  */
  public function __construct(array $data)
  {
    $this->data = $data;
  }
}
