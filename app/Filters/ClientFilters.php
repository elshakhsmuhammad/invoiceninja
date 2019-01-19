<?php

namespace App\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ClientFilters
 */
class ClientFilters extends QueryFilters
{

    /**
     * Filter by balance
     *     
     * @param  string $balance 
     * @return Illuminate\Database\Query\Builder 
     */
    public function balance(string $balance): Builder
    {
        $parts = $this->split($balance);

        return $this->builder->where('balance', $parts->operator, $parts->value);
    }

    /**
     * Filter between balances
     * 
     * @param  string balance
     * @return Illuminate\Database\Query\Builder
     */
    public function between_balance(string $balance): Builder
    {
        $parts = explode(":", $balance);

        return $this->builder->whereBetween('balance', [$parts[0], $parts[1]]);
    }

    /**
     * Filter based on search text
     * 
     * @param  string query filter
     * @return Illuminate\Database\Query\Builder
     */
    public function filter(string $filter = '') : Builder
    {
        if(strlen($filter) == 0)
            return $this->builder;

        return  $this->builder->where(function ($query) use ($filter) {
                    $query->where('clients.name', 'like', '%'.$filter.'%')
                          ->orWhere('clients.id_number', 'like', '%'.$filter.'%')
                          ->orWhere('client_contacts.first_name', 'like', '%'.$filter.'%')
                          ->orWhere('client_contacts.last_name', 'like', '%'.$filter.'%')
                          ->orWhere('client_contacts.email', 'like', '%'.$filter.'%');
                });
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted
     * 
     * @param  string filter
     * @return Illuminate\Database\Query\Builder
     */
    public function status(string $filter = '') : Builder
    {
        if(strlen($filter) == 0)
            return $this->builder;

        $table = 'clients';
        $filters = explode(',', $filter);

        return $this->builder->where(function ($query) use ($filters, $table) {
            $query->whereNull($table . '.id');

            if (in_array(parent::STATUS_ACTIVE, $filters)) {
                $query->orWhereNull($table . '.deleted_at');
            }

            if (in_array(parent::STATUS_ARCHIVED, $filters)) {
                $query->orWhere(function ($query) use ($table) {
                    $query->whereNotNull($table . '.deleted_at');

                    if (! in_array($table, ['users'])) {
                        $query->where($table . '.is_deleted', '=', 0);
                    }
                });
            }

            if (in_array(parent::STATUS_DELETED, $filters)) {
                $query->orWhere($table . '.is_deleted', '=', 1);
            }
        });
    }

    /**
     * Sorts the list based on $sort
     * 
     * @param  string sort formatted as column|asc
     * @return Illuminate\Database\Query\Builder
     */
    public function sort(string $sort) : Builder
    {
        $sort_col = explode("|", $sort);
        return $this->builder->orderBy($sort_col[0], $sort_col[1]);
    }

    /**
     * Returns the base query
     * 
     * @param  int company_id
     * @return Illuminate\Database\Query\Builder
     */
    public function baseQuery(int $company_id) : Builder
    {
        $query = DB::table('clients')
            ->join('companies', 'companies.id', '=', 'clients.company_id')
            ->join('client_contacts', 'client_contacts.client_id', '=', 'clients.id')
            ->where('clients.company_id', '=', $company_id)
            ->where('client_contacts.is_primary', '=', true)
            ->where('client_contacts.deleted_at', '=', null)
            //->whereRaw('(clients.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
            ->select(
                DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
                DB::raw("CONCAT(COALESCE(client_contacts.first_name, ''), ' ', COALESCE(client_contacts.last_name, '')) contact"),
                'clients.id',
                'clients.name',
                'clients.private_notes',
                'client_contacts.first_name',
                'client_contacts.last_name',
                'clients.balance',
                'clients.last_login',
                'clients.created_at',
                'clients.created_at as client_created_at',
                'client_contacts.phone',
                'client_contacts.email',
                'clients.deleted_at',
                'clients.is_deleted',
                'clients.user_id',
                'clients.id_number'
            );

            return $query;
    }

}