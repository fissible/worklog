<?php
/**
 *
 */

namespace Worklog\Testing;

trait DatabaseTransactions
{
    /**
     * Handle database transactions on the specified connections.
     *
     * @return void
     */
    public function beginDatabaseTransaction()
    {
        $this->db->begin_transaction();
        $this->beforeApplicationDestroyed(function () {
            $this->db->rollback();
        });
    }
}
