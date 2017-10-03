<?php

namespace UMFlint\Repository\Traits;

trait SoftDeletes
{
    /**
     * Apply a query scope to get deleted entities.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return $this
     */
    public function withTrashed()
    {
        return $this->scopeQuery(function ($query) {
            return $query->withTrashed();
        });
    }

    /**
     * Delete a entity by id.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @param      $id
     * @param bool $force
     * @return mixed
     */
    public function delete($id, $force = false)
    {
        $this->applyScope();
        $model = $this->find($id);
        $this->resetModel();

        if ($force) {
            return $model->forceDelete();
        }

        return $model->delete();
    }

    /**
     * Restore a entity by id.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @param $id
     * @return int
     */
    public function restore($id)
    {
        $this->applyScope();
        $model = $this->find($id);
        $this->resetModel();

        return $model->restore();
    }
}