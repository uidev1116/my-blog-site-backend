export interface BannerItemInterface {
  /**
   * バナーID
   */
  id: string;
  media_banner_type: BannerType;
  /**
   * ステータス
   */
  media_banner_status: 'true' | 'false';
  /**
   * 開始日
   */
  media_banner_datestart: string;
  /**
   * 開始時間
   */
  media_banner_timestart: string;
  /**
   * 終了日
   */
  media_banner_dateend: string;
  /**
   * 終了時間
   */
  media_banner_timeend: string;
}

export interface ImageBannerItem extends BannerItemInterface {
  media_banner_type: 'image';
  /**
   * メディアID
   */
  media_banner_mid?: number;
  /**
   * 代替テキスト
   */
  media_banner_alt: string;
  /**
   * ランドスケープ
   */
  media_banner_landscape?: boolean;
  /**
   * リンク
   */
  media_banner_link: string;
  /**
   * オーバーライドリンク
   */
  media_banner_override_link: string;
  /**
   * プレビュー画像
   */
  media_banner_preview: string;
  /**
   * ターゲット
   * @default false
   */
  media_banner_target: 'true' | 'false';
  /**
   * 属性1
   */
  media_banner_attr1?: string;
  /**
   * 属性2
   */
  media_banner_attr2?: string;
}

export interface SourceBannerItem extends BannerItemInterface {
  media_banner_type: 'source';
  /**
   * ソース
   */
  media_banner_source: string;
}

export type BannerItem = ImageBannerItem | SourceBannerItem;

export function isImageBannerItem(item: BannerItem): item is ImageBannerItem {
  return item.media_banner_type === 'image';
}

export function isSourceBannerItem(item: BannerItem): item is SourceBannerItem {
  return item.media_banner_type === 'source';
}

export type SortableBannerItem = {
  id: string;
  data: BannerItem | null;
};

export type BannerType = 'image' | 'source';
