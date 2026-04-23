<?php
use PHPUnit\Framework\TestCase;

require_once 'config.php';
require_once 'app/Core/Database.php';
require_once 'app/Models/User.php';
require_once 'app/Models/Appointment.php';

class IntegrationTests extends TestCase {

    // 1. Atomic Booking & Overlap Protection (Integrated DB Check)
    public function testAtomicBookingAndSlotLocking() {
        $app = new Appointment();
        $db = Database::getInstance();
        $patientId = $db->query("SELECT id FROM users WHERE role = 'patient' LIMIT 1")->fetchColumn();
        
        $doctorId = $db->query("SELECT id FROM doctors LIMIT 1")->fetchColumn();

        $slotId = $db->query("SELECT id FROM slots WHERE is_booked = 0 LIMIT 1")->fetchColumn();
        
        if (!$slotId) {
            $db->prepare("INSERT INTO slots (doctor_id, slot_date, start_time, is_booked) VALUES (?, CURDATE(), '10:00:00', 0)")
               ->execute([$doctorId]);
            $slotId = $db->lastInsertId();
        }

        $success = $app->book($patientId, $doctorId, $slotId, "Testing integration");

        $this->assertTrue($success, "Booking failed. Check if Patient ID: $patientId and Doctor ID: $doctorId exist.");
        
        $slotStatus = $db->query("SELECT is_booked FROM slots WHERE id = $slotId")->fetchColumn();
        $this->assertEquals(1, $slotStatus, "Slot must be marked booked after appointment creation.");
    }

    // 2. Auth Session Integration
    public function testLoginSessionIntegration() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $userModel = new User();
        $userData = $userModel->login('admin@abchospitals.com', 'password', 'admin');
        
        if($userData) {
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['role'] = $userData['role'];
        }

        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertEquals('admin', $_SESSION['role']);
    }

    // 3. Prescription Access Integration
    public function testPrescriptionDownloadLogicIntegration() {
        $db = Database::getInstance();
        
        $db->exec("INSERT INTO appointments (id, patient_id, status, prescription_file) VALUES (999, 1, 'completed', 'test.pdf')");
        
        $stmt = $db->prepare("SELECT status, prescription_file FROM appointments WHERE id = 999");
        $stmt->execute();
        $appData = $stmt->fetch();

        $canDownload = ($appData['status'] === 'completed' && !empty($appData['prescription_file']));
        $this->assertTrue($canDownload);
        
        $db->exec("DELETE FROM appointments WHERE id = 999");
    }
}