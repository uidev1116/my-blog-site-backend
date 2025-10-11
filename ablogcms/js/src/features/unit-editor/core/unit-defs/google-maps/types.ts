export interface GoogleMapsAttributes {
  lat: number;
  lng: number;
  zoom: number;
  msg: string;
  size: string;
  // ストリートビュー関連
  view_activate: boolean;
  view_pitch: number;
  view_zoom: number;
  view_heading: number;
}
