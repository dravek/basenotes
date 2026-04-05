<?php
<?php
/**
 * Copyright (c) 2026 David Carrillo <dravek@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

require dirname(__DIR__) . '/src/Util/Clock.php';
require dirname(__DIR__) . '/src/Util/Id.php';
require dirname(__DIR__) . '/src/Util/Validate.php';
require dirname(__DIR__) . '/src/Util/Slug.php';
require dirname(__DIR__) . '/src/Auth/Password.php';
require dirname(__DIR__) . '/src/Auth/Token.php';
require dirname(__DIR__) . '/src/Auth/RecoveryCode.php';
require dirname(__DIR__) . '/src/Repos/NoteRepository.php';
require dirname(__DIR__) . '/src/Repos/TokenRepository.php';
require dirname(__DIR__) . '/src/Repos/RecoveryCodeRepository.php';
require dirname(__DIR__) . '/src/Repos/TagRepository.php';
require dirname(__DIR__) . '/src/Repos/NoteTagRepository.php';
