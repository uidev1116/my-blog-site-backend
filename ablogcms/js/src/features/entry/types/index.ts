export type EntryStatus = 'open' | 'close' | 'draft' | 'trash';
export type EntryApproval = 'pre_approval' | 'none';
export type EntryFormStatus = 'open' | 'close';

export interface EntryType extends Record<string, unknown> {
  id: number;
  code: string;
  sort: number;
  status: EntryStatus;
  approval?: EntryApproval;
  form_status?: EntryFormStatus;
  title: string;
  link: string;
  url: string;
  datetime: Date;
  updated_datetime: Date;
  posted_datetime: Date;
  start_datetime: Date;
  end_datetime: Date;
  members_only: boolean;
  indexing: boolean;
  primary_image?: {
    path: string;
    thumbnail: string;
    width: number;
    height: number;
    alt: string;
  };
  tags: string[];
  category?: {
    id: number;
    name: string;
    url: string;
  };
  blog: {
    id: number;
    name: string;
    url: string;
  };
  user: {
    id: number;
    name: string;
    icon: string;
    url: string;
  };
  form?: {
    id: number;
    code: string;
    name: string;
  };
  lockUser?: {
    id: number;
    name: string;
  };
  actions: string[];
}

export type Axis = 'self' | 'descendant-or-self';

export type EntrySortType = 'entry' | 'user' | 'category';

export interface EntrySort {
  enabled: boolean;
  type: EntrySortType;
  context: {
    name: string;
  } | null;
  permissions: {
    entry: boolean;
    user: boolean;
    category: boolean;
    otherUser: boolean;
  };
}

export interface EntriesResponse {
  ignoredFilters: string[];
  entries: EntryType[];
  totalItems: number;
  bulkActions: string[];
  sort: EntrySort;
}
