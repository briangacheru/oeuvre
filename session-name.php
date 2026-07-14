<?php
/**
 * Gives the writer (root/share) interface its own session cookie, isolated
 * from the admin (sudo) interface's sudo/session-name.php. Without this,
 * both interfaces use PHP's default session name/path and end up sharing
 * one session store, so logging out of one silently logs out the other.
 *
 * Must be required before session_start() is called.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('itasker_writer');
}
