export type ModuleStatus = 'open' | 'close';
export type ModuleScope = 'global' | 'local';

export interface ModuleType {
  id: number;
  identifier: string;
  name: string;
  label: string;
  status: ModuleStatus;
  scope: ModuleScope;
  cache: number;
  custom_field: boolean;
  layout_use: boolean;
  api_use: boolean;
  created_datetime: Date | null;
  updated_datetime: Date | null;
  blog_id: number;
  blog?: {
    id: number;
    name: string;
    code: string;
  };
  actions: string[];
}

export interface ModulesResponse {
  modules: ModuleType[];
  bulkActions: string[];
}
