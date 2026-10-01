<?php
/**
 * Event Dispatcher — legacy compatibility for notification system.
 * Connects model events to notification listeners.
 */

if (!class_exists('EventDispatcher')) {
    class EventDispatcher {
        private static $listeners = [];

        public static function addListener($event, $callable) {
            self::$listeners[$event][] = $callable;
        }

        public static function dispatch($event, $data = []) {
            foreach (self::$listeners[$event] ?? [] as $callable) {
                call_user_func($callable, $data);
            }
        }
    }
}