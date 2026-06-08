<?php
class Tracker
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function logSymptoms($userId, $date, $symptoms)
    {
        // symptoms is array
        $data = [
            'user_id' => $userId,
            'log_date' => $date,
            'symptoms' => json_encode($symptoms),
        ];

        // Check if exists
        $exists = $this->db->fetch("SELECT id FROM symptom_logs WHERE user_id = :uid AND log_date = :date", [':uid' => $userId, ':date' => $date]);

        if ($exists) {
            return $this->db->update('symptom_logs', ['symptoms' => json_encode($symptoms)], "id = :id", [':id' => $exists['id']]);
        } else {
            return $this->db->insert('symptom_logs', $data);
        }
    }

    public function getCyclePrediction($userId)
    {
        // Simple logic: Last period date + cycle length
        $profile = $this->db->fetch("SELECT cycle_length, last_period_date FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);

        if (!$profile || !$profile['last_period_date']) {
            return null;
        }

        $last = new DateTime($profile['last_period_date']);
        $cycle = $profile['cycle_length'] ?: 28;

        $nextPeriod = clone $last;
        $nextPeriod->modify("+$cycle days");

        $ovulation = clone $nextPeriod;
        $ovulation->modify("-14 days"); // Rough estimate

        return [
            'last_period' => $last->format('Y-m-d'),
            'next_period' => $nextPeriod->format('Y-m-d'),
            'ovulation' => $ovulation->format('Y-m-d')
        ];
    }
}
