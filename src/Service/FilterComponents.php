<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Service;

final class FilterComponents
{
    public const MULTI_SELECT = 'ViewsTheme:Filter:MultiSelect';

    public const BOOLEAN = 'ViewsTheme:Filter:Boolean';

    public const RATING = 'ViewsTheme:Filter:Rating';

    public const RANGE = 'ViewsTheme:Filter:Range';

    private function __construct()
    {
    }
}
