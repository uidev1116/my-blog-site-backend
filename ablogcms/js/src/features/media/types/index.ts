import * as actions from '../stores/actions';

export type MediaType = 'image' | 'file' | 'svg';

export type MediaViewFileType = 'all' | 'image' | 'file';

export type MediaItem = {
  media_status: string;
  media_pdf_page: string;
  media_title: string;
  media_thumbnail: string;
  media_label: string;
  media_last_modified: string;
  media_datetime: string;
  media_id: string;
  media_bid: string;
  media_blog_name: string;
  media_user_id: string;
  media_user_name: string;
  media_last_update_user_id: string;
  media_last_update_user_name: string;
  media_size: string;
  media_filesize: number;
  media_path: string;
  media_root_path: string;
  media_pdf: string;
  media_original: string;
  media_edited: string;
  media_permalink: string;
  media_type: MediaType;
  media_ext: string;
  media_caption: string;
  media_link: string;
  media_alt: string;
  media_text: string;
  media_focal_point: string;
  media_landscape?: 'horizontal' | 'vertical';
  media_editable: boolean;
  media_icon: string;
  media_icon_width?: string;
  media_icon_height?: string;
  checked: boolean;
  width?: number;
  height?: number;
};

export interface MediaAjaxConfig {
  page?: number;
  tag?: string;
  keyword?: string;
  order?: string;
  limit?: number;
  date?: string;
  filetype?: MediaViewFileType;
  fileext?: string;
  year?: string;
  month?: string;
  owner?: boolean;
  blogAxis?: boolean;
}

export interface MediaStateProps {
  items: MediaItem[];
  tags: string[];
  extensions: string[];
  actions: typeof actions;
  item: MediaItem | null;
  largeSize: number;
  formToken: string;
  archives: string[];
  label: string;
  // radioMode?: boolean;
  lastPage: number;
  total: number;
  config: Required<MediaAjaxConfig>;
  selectedTags: string[];
  // filetype?: MediaViewFileType;
  loading: boolean;
}

export interface MediaTag {
  value: string;
  label: string;
}

export type FocalPoint = [number, number];

export interface FocalPointCoordinates {
  x: number;
  y: number;
}
