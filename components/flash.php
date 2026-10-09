<?php

declare(strict_types=1);

if (!function_exists('flash_success')) {
    function flash_success(string $message): void
    {
        $_SESSION['flash_success'] = $message;
    }
}

if (!function_exists('flash_error')) {
    function flash_error(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }
}

if (!function_exists('pull_flash')) {
    function pull_flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return $value;
    }
}

if (!function_exists('render_flash')) {
    function render_flash(): void
    {
        $success = pull_flash('flash_success');
        $error = pull_flash('flash_error');

        if (!$success && !$error) {
            return;
        }

        static $assetsRendered = false;
        if (!$assetsRendered) {
            $assetsRendered = true;
            echo '<style>
                .fm-toast-wrap{
                    position:fixed;
                    top:0.9rem;
                    right:0.9rem;
                    z-index:2000;
                    display:flex;
                    flex-direction:column;
                    gap:0.55rem;
                    width:min(360px, calc(100vw - 1.8rem));
                    pointer-events:none;
                }
                .fm-toast{
                    pointer-events:auto;
                    border-radius:12px;
                    border:1px solid #d8e8e4;
                    background:#ffffff;
                    color:#0f172a;
                    box-shadow:0 18px 32px -24px rgba(15, 23, 42, 0.45);
                    padding:0.72rem 0.86rem;
                    display:flex;
                    align-items:flex-start;
                    gap:0.7rem;
                    transform:translateX(24px);
                    opacity:0;
                    animation:fmToastIn .22s ease forwards, fmToastOut .22s ease 4.2s forwards;
                }
                .fm-toast-success{
                    border-color:#a7f3d0;
                    background:#ecfdf5;
                    color:#065f46;
                }
                .fm-toast-error{
                    border-color:#fecaca;
                    background:#fef2f2;
                    color:#991b1b;
                }
                .fm-toast-text{
                    flex:1 1 auto;
                    font-size:0.92rem;
                    line-height:1.35;
                }
                .fm-toast-close{
                    border:0;
                    background:transparent;
                    color:inherit;
                    font-size:1rem;
                    line-height:1;
                    padding:0.05rem 0.2rem;
                    cursor:pointer;
                    opacity:0.8;
                }
                .fm-toast-close:hover{opacity:1;}
                @keyframes fmToastIn{
                    to{transform:translateX(0);opacity:1;}
                }
                @keyframes fmToastOut{
                    to{transform:translateX(24px);opacity:0;visibility:hidden;}
                }
            </style>';
        }

        echo '<div class="fm-toast-wrap" aria-live="polite" aria-atomic="true">';
        if ($success) {
            echo '<div class="fm-toast fm-toast-success" role="status">'
                . '<div class="fm-toast-text">' . e($success) . '</div>'
                . '<button type="button" class="fm-toast-close" aria-label="Close" onclick="this.parentNode.remove()">&times;</button>'
                . '</div>';
        }
        if ($error) {
            echo '<div class="fm-toast fm-toast-error" role="alert">'
                . '<div class="fm-toast-text">' . e($error) . '</div>'
                . '<button type="button" class="fm-toast-close" aria-label="Close" onclick="this.parentNode.remove()">&times;</button>'
                . '</div>';
        }
        echo '</div>';
    }
}
