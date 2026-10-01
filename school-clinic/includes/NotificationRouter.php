<?php
/**
 * Notification Router — legacy compatibility.
 * Routes notification events to appropriate listeners.
 */

if (!class_exists('NotificationRouter')) {
    class NotificationRouter {
        private $pdo;
        private $dispatcher;

        public function __construct($pdo, $dispatcher = null) {
            $this->pdo = $pdo;
            $this->dispatcher = $dispatcher ?: new EventDispatcher();
        }

        public function registerListeners() {
            // Student registration notification
            $this->dispatcher->addListener('student_registered', function($data) {
                // Create notification for student advisor
                // ... implementation depends on specific needs
            });

            // Employee registration notification
            $this->dispatcher->addListener('employee_registered', function($data) {
                // Create notification for HR
                // ... implementation depends on specific needs
            });

            // Login event
            $this->dispatcher->addListener('user_login', function($data) {
                // Log activity, create session notifications
                // ... implementation depends on specific needs
            });
        }

        public function route($event, $data = []) {
            $this->dispatcher->dispatch($event, $data);
        }
    }
}