<?php

class AutomationOrchestrator
{
    private $db;
    private $userModel;
    private $mailer;
    private $mealPlanner;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->userModel = new User();
        $this->mailer = new Mailer();
        $this->mealPlanner = new MealPlanner();
    }

    public function handlePurchase($orderData, $assessmentData, $type = 'pcos')
    {
        error_log("Automation: Handling $type Purchase for " . ($orderData['email'] ?? 'unknown'));

        // 0. Initialize Logger
        require_once __DIR__ . '/ActivityLogger.php';
        $logger = new ActivityLogger();

        // 1. Check if user exists
        $email = $orderData['email'];
        $name = $orderData['name'];

        $userId = null;
        $existingUser = $this->userModel->findByEmail($email);
        $newCredentials = null;
        $isNewUser = false;

        if ($existingUser) {
            $userId = $existingUser['id'];
            error_log("Automation: User exists ID $userId");
            // Activate user and upgrade to customer on purchase
            $this->userModel->update($userId, [
                'type' => 'customer',
                'status' => 'active'
            ]);
        } else {
            // Create New User
            $isNewUser = true;
            $creds = $this->userModel->generateCredentials($name);
            $newCredentials = $creds;

            $userData = [
                'first_name' => $name,
                'name' => $name,
                'email' => $email,
                'username' => $creds['username'],
                'password_hash' => password_hash($creds['password'], PASSWORD_DEFAULT),
                'type' => 'customer',
                'status' => 'active',
                'condition_type' => $type
            ];

            $userId = $this->userModel->createUser($userData);
            if ($userId) {
                error_log("Automation: Created user ID $userId");
                $logger->log($userId, 'registration', ['method' => 'automation_webhook', 'funnel' => $type]);
            } else {
                error_log("Automation: Failed to create user for $email");
                return ['success' => false, 'error' => 'Failed to create user'];
            }
        }

        // 1b. Calculate plan duration
        $productName = $orderData['product'] ?? "$type Plan";
        $planDuration = intval($orderData['plan_duration'] ?? 0);
        if ($planDuration === 0) {
            if (stripos($productName, '90') !== false) {
                $planDuration = 90;
            } elseif (stripos($productName, '30') !== false) {
                $planDuration = 30;
            } else {
                $amount = floatval($orderData['amount'] ?? 0);
                $planDuration = ($amount > 150) ? 90 : 30; // USD 197 vs 97
            }
        }
        $planStartDate = date('Y-m-d H:i:s');
        $planEndDate = date('Y-m-d H:i:s', strtotime("+{$planDuration} days"));

        // Update user with plan dates
        $this->userModel->update($userId, [
            'plan_duration' => $planDuration,
            'plan_start_date' => $planStartDate,
            'plan_end_date' => $planEndDate
        ]);

        // 1c. Record Sale (CRITICAL for Credentials Display) — with duplicate guard
        $txId = $orderData['transaction_id'] ?? 'MAN_' . uniqid();
        $txRef = $orderData['tx_ref'] ?? $txId;

        // Check if sale already exists (prevents duplicates from dual-path creation)
        $existingSale = $this->db->fetch(
            "SELECT id FROM sales WHERE transaction_id = ? OR tx_ref = ?",
            [$txId, $txRef]
        );

        if (!$existingSale) {
            $this->db->insert('sales', [
                'id' => 'ORD_' . uniqid(),
                'user_id' => $userId,
                'transaction_id' => $txId,
                'tx_ref' => $txRef,
                'email' => $email,
                'name' => $name,
                'product_type' => $type,
                'product_name' => $productName,
                'amount' => $orderData['amount'] ?? 0,
                'currency' => $orderData['currency'] ?? 'USD',
                'payment_status' => 'completed',
                'plan_duration' => $planDuration,
                'plan_start_date' => $planStartDate,
                'plan_end_date' => $planEndDate,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            error_log("Automation: Recorded sale for user $userId (Plan: {$planDuration} days, Expires: $planEndDate)");
            $logger->log($userId, 'purchase', ['amount' => $orderData['amount'], 'product' => $orderData['product'], 'tx_id' => $txId]);
        } else {
            error_log("Automation: Sale already exists for tx_ref $txRef — skipping duplicate insert");
        }

        // 2. Save/Update Member Profile
        $this->saveMemberProfile($userId, $assessmentData, $type);

        // 3. Generate Plan
        try {
            $startDate = new DateTime();
            $nextSunday = new DateTime('next sunday');
            if ($startDate->diff($nextSunday)->days < 4) {
                $nextSunday->modify('+1 week');
            }
            $this->mealPlanner->generateWeeklyPlanRange($userId, $startDate->format('Y-m-d'), $nextSunday->format('Y-m-d'));
        } catch (Exception $e) {
            error_log("Automation Plan Gen Error: " . $e->getMessage());
        }

        // 4. Send Welcome Email
        if ($newCredentials) {
            $subject = "Welcome to your 1wellness " . strtoupper($type) . " Plan — Your Login Details Inside!";
            $loginUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? '1wellness.club') . "/member/login.html";
            $planLabel = $planDuration . '-Day ' . strtoupper($type) . ' Plan';
            $body = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #0f3922; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                        <h1 style='color: #F4F1EA; margin: 0; font-size: 24px;'>Welcome, {$name}!</h1>
                        <p style='color: #8DA38D; margin: 10px 0 0;'>Your {$planLabel} is ready</p>
                    </div>
                    <div style='background: #ffffff; padding: 30px; border: 1px solid #e0e0e0;'>
                        <p style='font-size: 16px; line-height: 1.6;'>Your personalized {$planLabel} has been generated and is waiting for you in the member area.</p>
                        <div style='background: #f8f6f2; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #0f3922;'>
                            <p style='margin: 0 0 12px; font-size: 14px; color: #666; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;'>Your Login Credentials</p>
                            <p style='margin: 8px 0; font-size: 16px;'>Email: <strong style='color: #0f3922;'>{$email}</strong></p>
                            <p style='margin: 8px 0; font-size: 16px;'>Password: <strong style='color: #0f3922;'>{$newCredentials['password']}</strong></p>
                        </div>
                        <p style='font-size: 13px; color: #999;'>Please save your password. For security, we recommend changing it after your first login.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$loginUrl}' style='background: #0f3922; color: #F4F1EA; padding: 16px 40px; text-decoration: none; border-radius: 30px; font-size: 16px; font-weight: bold; display: inline-block;'>Go to My Dashboard</a>
                        </div>
                        <p style='font-size: 14px; color: #666; text-align: center;'>Plan Duration: <strong>{$planDuration} days</strong> (Expires: " . date('F j, Y', strtotime($planEndDate)) . ")</p>
                    </div>
                    <div style='background: #f8f6f2; padding: 20px; text-align: center; border-radius: 0 0 12px 12px; border: 1px solid #e0e0e0; border-top: 0;'>
                        <p style='margin: 0; font-size: 12px; color: #999;'>1wellness — Your Natural Healing Journey</p>
                    </div>
                </div>
            ";
            try {
                $this->mailer->send($email, $subject, $body);
                error_log("Automation: Sent welcome email to $email with password");
            } catch (Exception $e) {
                error_log("Automation Email Error: " . $e->getMessage());
            }
        }

        // 5. Auto-Login Token
        $token = bin2hex(random_bytes(16));
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->db->query("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)", [$userId, $token, $expiry]);

        return [
            'success' => true,
            'user_id' => $userId,
            'username' => $newCredentials['username'] ?? ($existingUser['username'] ?? $email), // Return username for frontend
            'auto_login_token' => $token,
            'redirect_url' => "/member/dashboard.php?autologin=$token",
            'credentials' => $newCredentials
        ];
    }

    public function handlePCOSPurchase($orderData, $assessmentData)
    {
        return $this->handlePurchase($orderData, $assessmentData, 'pcos');
    }

    public function handleWeightPurchase($orderData, $assessmentData)
    {
        return $this->handlePurchase($orderData, $assessmentData, 'weight');
    }

    public function handleAcnePurchase($orderData, $assessmentData)
    {
        return $this->handlePurchase($orderData, $assessmentData, 'acne');
    }

    public function handleMensPurchase($orderData, $assessmentData)
    {
        return $this->handlePurchase($orderData, $assessmentData, 'mens');
    }

    private function saveMemberProfile($userId, $data, $type = 'pcos')
    {
        // Calculate Subscription Duration
        $durationDays = 30; // Default
        // Check if 90-day plan is mentioned in product name or passed data
        if (
            (isset($data['product']) && stripos($data['product'], '90') !== false) ||
            (isset($data['plan']) && stripos($data['plan'], '90') !== false) ||
            (isset($data['amount']) && $data['amount'] > 150) // Fallback heuristic (USD 197 vs 97)
        ) {
            $durationDays = 90;
        }

        $subscriptionData = [
            'subscription_tier' => $durationDays . '-day',
            'subscription_status' => 'active',
            'start_date' => date('Y-m-d'),
            'subscription_expiry' => date('Y-m-d', strtotime("+$durationDays days"))
        ];

        // Default values
        $profileData = [
            'user_id' => $userId,
            'pcos_type' => $data['pcos_type'] ?? ($type === 'pcos' ? 'General' : null),
            'cycle_length' => $data['cycle_length'] ?? 28,
            'last_period_date' => $data['last_period_date'] ?? date('Y-m-d'),
            'allergies' => is_array($data['allergies'] ?? 'None') ? json_encode($data['allergies']) : ($data['allergies'] ?? 'None'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Check if profile exists
        $exists = $this->db->fetch("SELECT id FROM member_profiles WHERE user_id = ?", [$userId]);

        if ($exists) {
            // Update existing profile + Subscription (Extend or Reset?)
            // For now, purchase = active subscription
            $updateData = array_merge($profileData, $subscriptionData);
            $this->db->update('member_profiles', $updateData, "id = :id", [':id' => $exists['id']]);
        } else {
            // New Profile
            $insertData = array_merge($profileData, $subscriptionData, ['created_at' => date('Y-m-d H:i:s')]);
            $this->db->insert('member_profiles', $insertData);
        }

        error_log("Automation: Updated subscription for User $userId expiry: " . $subscriptionData['subscription_expiry']);
    }
}
