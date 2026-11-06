<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

arch('app uses strict types')->expect('App')->toUseStrictTypes();
