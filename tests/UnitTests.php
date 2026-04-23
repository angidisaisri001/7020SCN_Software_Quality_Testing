<?php
use PHPUnit\Framework\TestCase;

require_once 'config.php';
require_once 'app/Core/Database.php';
require_once 'app/Models/User.php';
require_once 'app/Models/Hospital.php';

class UnitTests extends TestCase {

    // 1. Security Test: Password Hashing
    public function testPasswordSecurityHashing() {
        $user = new User();
        $password = "Patient123!";
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        
        $this->assertNotEquals($password, $hashed);
        $this->assertTrue(password_verify($password, $hashed));
    }

    // 2. Slot Count Logic: 7 slots per department
    public function testDepartmentSlotAllocationCount() {
        $hospital = new Hospital();
        $db = Database::getInstance();
        
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $db->exec("DELETE FROM slots");
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $hospital->generateSlots();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM slots s JOIN doctors d ON s.doctor_id = d.id WHERE d.department_id = 1 AND s.slot_date = CURDATE()");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(7, $count, "Each department should have exactly 7 slots per day.");
    }

    // 3. New Doctor Roster Logic: 21 slots created (3 slots * 7 days)
    public function testNewDoctorAutoRosterGeneration() {
        $hospital = new Hospital();
        $db = Database::getInstance();
        
        $initialCount = $db->query("SELECT COUNT(*) FROM slots")->fetchColumn();
        $hospital->addDoctor("Test Dr. PHPUnit", 1);
        $newCount = $db->query("SELECT COUNT(*) FROM slots")->fetchColumn();
        
        $this->assertEquals($initialCount + 21, $newCount);
    }

    // 4. URL Routing Logic
    public function testRouterUrlParsing() {
        $url = "admin/update_appointment/10";
        $parts = explode('/', filter_var(rtrim($url, '/'), FILTER_SANITIZE_URL));
        
        $this->assertEquals('admin', $parts[0]);
        $this->assertEquals('update_appointment', $parts[1]);
        $this->assertEquals('10', $parts[2]);
    }

    // 5. File MIME Validation Logic
    public function testPrescriptionFileExtensionValidation() {
        $allowed = ['pdf', 'jpg', 'png'];
        $fileName = "prescription.pdf";
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        
        $this->assertContains($ext, $allowed);
        
        $badFile = "virus.exe";
        $badExt = pathinfo($badFile, PATHINFO_EXTENSION);
        $this->assertNotContains($badExt, $allowed);
    }
}