<?php
class VPAB
{
    private $db; // Database connection
    private $session; // Session reference

    public function __construct($db, &$session)
    {
        $this->db = $db;
        $this->session = &$session; // Pass session as a reference
    }

    // 1. Fetch all buyers from the database
    public function getAllBuyers()
    {
        $query = "SELECT id, username FROM user WHERE role = 'Buyer'";
        $result = mysqli_query($this->db, $query);
        $buyers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $buyers[] = $row;
        }
        return $buyers;
    }

    // 2. Switch to Buyer Role
    public function switchToBuyer($buyerId)
    {
        // Validate the buyer ID
        $stmt = $this->db->prepare("SELECT id, username FROM user WHERE id = ? AND role = 'Buyer'");
        $stmt->bind_param('i', $buyerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $buyer = $result->fetch_assoc();        
        if ($buyer) {            
            // Store original admin session
            $this->session['original_role'] = $this->session['role'];
            $this->session['original_user_id'] = $this->session['user_id'];
            $this->session['original_username'] = $this->session['username'];

            // Set buyer session
            $this->session['role'] = 'Buyer';
            $this->session['user_id'] = $buyer['id'];
            $this->session['username'] = $buyer['username'];
            $this->session['is_impersonating'] = true;

            return true;
        } else {
            return false; // Invalid buyer ID
        }
    }

    // 3. Restore Admin Role
    public function restoreAdminRole()
    {
        if (isset($this->session['is_impersonating']) && $this->session['is_impersonating']) {
            // Restore original session
            $this->session['role'] = $this->session['original_role'];
            $this->session['user_id'] = $this->session['original_user_id'];
            $this->session['username'] = $this->session['original_username'];

            // Clean up session variables
            unset($this->session['original_role']);
            unset($this->session['original_user_id']);
            unset($this->session['original_username']);
            unset($this->session['is_impersonating']);

            return true;
        }
        return false;
    }

    // 4. Check if Admin is Impersonating
    public function isImpersonating()
    {
        return isset($this->session['is_impersonating']) && $this->session['is_impersonating'];
    }

    // 5. Get Current Viewing User (for display)
    public function getCurrentUsername()
    {        
        return $this->session['username'];
    }
}
