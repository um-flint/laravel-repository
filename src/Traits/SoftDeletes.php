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
        $this->withTrashed();

        return $this;
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
        $model = $this->find($id);

        if (method_exists($this, 'beforeDelete')) {
            call_user_func_array([$this, 'beforeDelete'], [$model]);
        }

        if ($force) {
            $deleted = $model->forceDelete();
        }else {
            $deleted = $model->delete();
        }

        if (method_exists($this, 'afterDelete')) {
            call_user_func_array([$this, 'afterDelete'], [$model, $deleted]);
        }

        return $deleted;
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
        $model = $this->find($id);

        if (method_exists($this, 'beforeRestore')) {
            call_user_func_array([$this, 'beforeRestore'], [$model]);
        }

        $restored = $model->restore();

        if (method_exists($this, 'beforeRestore')) {
            call_user_func_array([$this, 'beforeRestore'], [$model]);
        }

        return $restored;
    }
}