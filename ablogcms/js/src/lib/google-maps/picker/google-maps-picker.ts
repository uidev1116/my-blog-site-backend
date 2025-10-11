import loadGoogleMap from '../loader';

export interface GoogleMapsValue {
  type: 'ROADMAP' | 'STREETVIEW';
  lat: number;
  lng: number;
  zoom: number;
  streetView: {
    heading: number;
    pitch: number;
    zoomView: number;
  };
}

export interface GoogleMapsPickerOptions {
  // DOM要素セレクターまたは要素自体
  latInput: string | HTMLInputElement;
  lngInput: string | HTMLInputElement;
  zoomInput: string | HTMLInputElement;
  pitchInput?: string | HTMLInputElement;
  zoomViewInput?: string | HTMLInputElement;
  headingInput?: string | HTMLInputElement;
  modeSwitcher?: string | HTMLInputElement;
  searchInput?: string | HTMLInputElement;
  searchButton?: string | HTMLButtonElement;
  mapRoot?: string | HTMLElement;

  // コールバック
  onChange?: (value: GoogleMapsValue | null) => void;

  // 設定
  apiKey: string;
  defaultValue?: Partial<GoogleMapsValue>;
  mapOptions?: google.maps.MapOptions;
}

const defaultOptions: GoogleMapsPickerOptions = {
  latInput: '.js-map_editable-lat, input[name^="map_lat_"]',
  lngInput: '.js-map_editable-lng, input[name^="map_lng_"]',
  zoomInput: '.js-map_editable-zoom, input[name^="map_zoom_"]',
  pitchInput: '.js-map_editable-pitch, input[name^="map_view_pitch_"]',
  zoomViewInput: '.js-map_editable-zoom-view, input[name^="map_view_zoom_"]',
  headingInput: '.js-map_editable-heading, input[name^="map_view_heading_"]',
  modeSwitcher: '.js-map_editable-activate, input[type="checkbox"][name^="map_view_activate_"]',
  searchInput: '.js-editable_map-search_text',
  searchButton: '.js-editable_map-search_button',
  mapRoot: '.js-map_editable-container',
  apiKey: ACMS.Config.googleApiKey,
};

export default class GoogleMapsPicker {
  private options: GoogleMapsPickerOptions;

  private google: typeof google | null = null;

  private googleMap: google.maps.Map | null = null;

  private googleMapMarker: google.maps.Marker | null = null;

  // 状態管理
  private value: GoogleMapsValue;

  // DOM要素
  private container: HTMLElement;

  private mapRoot: HTMLElement;

  private latInput!: HTMLInputElement;

  private lngInput!: HTMLInputElement;

  private zoomInput!: HTMLInputElement;

  private pitchInput: HTMLInputElement | null = null;

  private zoomViewInput: HTMLInputElement | null = null;

  private headingInput: HTMLInputElement | null = null;

  private modeSwitcher: HTMLInputElement | null = null;

  private searchInput: HTMLInputElement | null = null;

  private searchButton: HTMLButtonElement | null = null;

  // イベントリスナー管理
  private eventRegistry: Map<
    Element | Document,
    Array<{
      type: string;
      listener: EventListener;
      options?: AddEventListenerOptions;
    }>
  > = new Map();

  constructor(elementOrSelector: string | HTMLElement, options: Partial<GoogleMapsPickerOptions>) {
    const container =
      typeof elementOrSelector === 'string'
        ? document.querySelector<HTMLElement>(elementOrSelector)
        : elementOrSelector;

    if (!container) {
      throw new Error('Container element not found');
    }

    this.container = container;
    this.options = {
      ...defaultOptions,
      ...options,
    };

    const latInput = this.getInputElement(this.options.latInput, this.container);
    const lngInput = this.getInputElement(this.options.lngInput, this.container);
    const zoomInput = this.getInputElement(this.options.zoomInput, this.container);
    const pitchInput = this.getInputElement(this.options.pitchInput, this.container);
    const zoomViewInput = this.getInputElement(this.options.zoomViewInput, this.container);
    const headingInput = this.getInputElement(this.options.headingInput, this.container);
    const modeSwitcher = this.getInputElement(this.options.modeSwitcher, this.container);
    const searchInput = this.getInputElement(this.options.searchInput, this.container);
    const searchButton = this.getButtonElement(this.options.searchButton, this.container);
    const mapRoot = this.getElement(this.options.mapRoot, this.container);
    // 必須要素のバリデーション
    if (!latInput) {
      throw new Error('Latitude input element is required');
    }

    if (!lngInput) {
      throw new Error('Longitude input element is required');
    }

    if (!zoomInput) {
      throw new Error('Zoom input element is required');
    }

    if (!mapRoot) {
      throw new Error('Map root element is required');
    }

    this.latInput = latInput;
    this.lngInput = lngInput;
    this.zoomInput = zoomInput;
    this.pitchInput = pitchInput;
    this.zoomViewInput = zoomViewInput;
    this.headingInput = headingInput;
    this.modeSwitcher = modeSwitcher;
    this.searchInput = searchInput;
    this.searchButton = searchButton;
    this.mapRoot = mapRoot;

    this.value = {
      type: 'ROADMAP',
      lat: 0,
      lng: 0,
      zoom: 0,
      streetView: {
        heading: 0,
        pitch: 0,
        zoomView: 0,
      },
      ...this.options.defaultValue,
    };

    if (mapRoot instanceof HTMLImageElement) {
      // compatibility
      const div = document.createElement('div');
      div.className = mapRoot.className;

      // style をコピー
      div.style.cssText = getComputedStyle(mapRoot).cssText;

      // サイズを固定
      div.style.width = `${mapRoot.width}px`;
      div.style.height = `${mapRoot.height}px`;
      mapRoot.parentNode?.replaceChild(div, mapRoot);
      this.mapRoot = div;
    }
  }

  async init(): Promise<GoogleMapsPicker> {
    if (this.google) {
      return this;
    }

    try {
      // Google Maps API読み込み
      this.google = await loadGoogleMap(this.options.apiKey);

      this.setValue();
      // イベントリスナー設定
      this.setupEventListeners();

      // マップ作成
      this.createMap(this.options.mapOptions);
      this.toggleStreetView();

      return this;
    } catch (error) {
      throw new Error(`Failed to initialize Google Maps: ${error}`);
    }
  }

  // DOM要素取得のヘルパーメソッド
  private getElement<T extends HTMLElement = HTMLElement>(
    elementOrSelector: string | T | undefined,
    context: HTMLElement = document.body
  ): T | null {
    if (!elementOrSelector) return null;

    if (typeof elementOrSelector === 'string') {
      // コンテナ自身がセレクタに一致するかチェック
      if (context.matches && context.matches(elementOrSelector)) {
        return context as T;
      }
      return context.querySelector<T>(elementOrSelector);
    }

    return elementOrSelector;
  }

  private getInputElement(
    elementOrSelector: string | HTMLInputElement | undefined,
    context: HTMLElement = document.body
  ): HTMLInputElement | null {
    const element = this.getElement(elementOrSelector, context);
    return element instanceof HTMLInputElement ? element : null;
  }

  private getButtonElement(
    elementOrSelector: string | HTMLButtonElement | undefined,
    context: HTMLElement = document.body
  ): HTMLButtonElement | null {
    const element = this.getElement(elementOrSelector, context);
    return element instanceof HTMLButtonElement ? element : null;
  }

  private setupEventListeners(): void {
    const handleLatLngChange = () => {
      if (!this.google) {
        return;
      }

      this.setValue();

      const center = new this.google.maps.LatLng(this.value.lat, this.value.lng);
      if (this.googleMap) {
        this.googleMap.panTo(center);
      }
      if (this.googleMapMarker) {
        this.googleMapMarker.setPosition(center);
      }
      this.emitChange();
    };
    this.latInput.addEventListener('change', handleLatLngChange);
    this.lngInput.addEventListener('change', handleLatLngChange);
    this.eventRegistry.set(this.latInput, [{ type: 'change', listener: handleLatLngChange }]);
    this.eventRegistry.set(this.lngInput, [{ type: 'change', listener: handleLatLngChange }]);

    // ズーム変更イベント
    if (this.zoomInput) {
      const handleZoomChange = () => {
        if (this.googleMap) {
          this.googleMap.setZoom(this.value.zoom);
          this.setValue();
          this.emitChange();
        }
      };
      this.zoomInput.addEventListener('change', handleZoomChange);
      this.eventRegistry.set(this.zoomInput, [{ type: 'change', listener: handleZoomChange }]);
    }

    // ストリートビュー有効化チェックボックス
    if (this.modeSwitcher) {
      const handleModeChange = () => {
        this.toggleStreetView();
        this.emitChange();
      };
      this.modeSwitcher.addEventListener('change', handleModeChange);
      this.eventRegistry.set(this.modeSwitcher, [{ type: 'change', listener: handleModeChange }]);
    }

    // 検索機能
    this.setupSearch();
  }

  private createMap(options: google.maps.MapOptions = {}): void {
    if (!this.google) {
      return;
    }

    const div = document.createElement('div');
    div.style.height = '100%';

    this.mapRoot.replaceChildren(div);

    const center = new this.google.maps.LatLng(this.value.lat, this.value.lng);

    this.googleMap = new this.google.maps.Map(div, {
      zoom: this.value.zoom,
      center,
      mapTypeId: this.google.maps.MapTypeId.ROADMAP,
      gestureHandling: 'cooperative',
      ...options,
    });

    this.registerMapEvents();
    this.createMarker();
  }

  private registerMapEvents(): void {
    if (!this.google || !this.googleMap) {
      return;
    }

    // ズームイベント
    if (this.zoomInput) {
      this.google.maps.event.addListener(this.googleMap, 'zoom_changed', () => {
        if (this.googleMap && this.zoomInput) {
          this.zoomInput.value = this.googleMap.getZoom().toString();
          this.setValue();
          this.emitChange();
        }
      });
    }
  }

  private unregisterMapEvents(): void {
    if (!this.google || !this.googleMap) {
      return;
    }
    this.google.maps.event.clearInstanceListeners(this.googleMap);
  }

  private createMarker(): void {
    if (!this.google || !this.googleMap) {
      return;
    }

    const center = this.googleMap.getCenter();
    this.googleMapMarker = new this.google.maps.Marker({
      position: center,
      map: this.googleMap,
      draggable: true,
    });

    this.google.maps.event.addListener(this.googleMapMarker, 'dragend', (event) => {
      this.setPosition(event.latLng);
      this.emitChange();
    });
  }

  private createPanorama(): google.maps.StreetViewPanorama | null {
    if (!this.google || !this.googleMap) {
      return null;
    }

    const center = this.googleMap.getCenter();
    const config: google.maps.StreetViewPanoramaOptions = {
      position: {
        lat: center.lat(),
        lng: center.lng(),
      },
      pov: {
        heading: this.value.streetView.heading,
        pitch: this.value.streetView.pitch,
      },
      zoom: this.value.streetView.zoomView,
    };

    // マップのdiv要素を取得
    const mapDiv = this.googleMap.getDiv();
    const panorama = new this.google!.maps.StreetViewPanorama(mapDiv, config);
    return panorama;
  }

  private registerStreetViewEvents(): void {
    if (!this.google || !this.googleMap) {
      return;
    }

    const panorama = this.googleMap.getStreetView();

    // POV変更イベント
    this.google.maps.event.addListener(panorama, 'pov_changed', () => {
      this.updateStreetViewInputs();
      this.emitChange();
    });

    // 位置変更イベント
    this.google.maps.event.addListener(panorama, 'position_changed', () => {
      const pos = panorama.getPosition();
      this.setPosition(pos);
      this.emitChange();
    });
  }

  private unregisterStreetViewEvents(): void {
    if (!this.google || !this.googleMap) {
      return;
    }
    const panorama = this.googleMap.getStreetView();
    this.google.maps.event.clearInstanceListeners(panorama);
  }

  private updateStreetViewInputs(): void {
    if (!this.googleMap) {
      return;
    }

    const streetView = this.googleMap.getStreetView();

    const pov = streetView.getPov();
    if (pov.pitch !== undefined && this.pitchInput) {
      this.pitchInput.value = pov.pitch.toString();
    }
    if (pov.heading !== undefined && this.headingInput) {
      this.headingInput.value = pov.heading.toString();
    }
    if (this.zoomViewInput) {
      this.zoomViewInput.value = streetView.getZoom().toString();
    }
    this.setValue();
  }

  private toggleStreetView(): void {
    const isStreetViewMode = this.modeSwitcher?.checked || false;

    if (isStreetViewMode) {
      this.activateStreetView();
    } else {
      this.deactivateStreetView();
    }

    this.setValue();
  }

  private activateStreetView(): void {
    if (!this.googleMap) {
      return;
    }

    const panorama = this.createPanorama();
    if (panorama) {
      this.googleMap.setStreetView(panorama);
      this.googleMap.setOptions({
        streetViewControl: true,
      });
      this.registerStreetViewEvents();
      this.updateStreetViewInputs();
      this.toggleUIElements();

      setTimeout(() => {
        if (panorama.getStatus() !== this.google!.maps.StreetViewStatus.OK) {
          alert('指定の位置にストリートビューがありません。ストリートビューの範囲にペグマンをドロップしてください。');
        }
      }, 1000);
    }
  }

  private deactivateStreetView(): void {
    // ストリートビューをクリア
    if (this.googleMap) {
      this.googleMap.setStreetView(null);
      this.googleMap.setOptions({
        streetViewControl: false,
      });
    }

    this.resetStreetViewInputs();
    this.unregisterStreetViewEvents();
    this.toggleUIElements();
  }

  private resetStreetViewInputs(): void {
    if (this.headingInput) {
      this.headingInput.value = '';
    }
    if (this.pitchInput) {
      this.pitchInput.value = '';
    }
    if (this.zoomViewInput) {
      this.zoomViewInput.value = '';
    }
    this.setValue();
  }

  private toggleUIElements(): void {
    const isStreetViewMode = this.modeSwitcher?.checked || false;

    const streetviewTable = this.container.querySelector<HTMLElement>('.js-streetview-table');
    const mapTable = this.container.querySelector<HTMLElement>('.js-map-table');

    if (streetviewTable) {
      streetviewTable.style.display = isStreetViewMode ? 'block' : 'none';
    }
    if (mapTable) {
      mapTable.style.display = isStreetViewMode ? 'none' : 'block';
    }
  }

  private setupSearch(): void {
    if (!this.searchInput || !this.searchButton) {
      return;
    }

    this.searchInput.disabled = false;
    this.searchButton.disabled = false;

    const handleClick = (event: Event) => {
      event.preventDefault();
      this.performSearch();
    };

    const handleKeydown = (event: KeyboardEvent) => {
      if (event.keyCode === 13) {
        event.preventDefault();
        this.searchButton?.click();
      }
    };

    this.searchButton.addEventListener('click', handleClick);
    this.searchInput.addEventListener('keydown', handleKeydown);
    this.eventRegistry.set(this.searchButton, [{ type: 'click', listener: handleClick }]);
    this.eventRegistry.set(this.searchInput, [{ type: 'keydown', listener: handleKeydown as EventListener }]);
  }

  private async performSearch(): Promise<void> {
    if (!this.google || !this.searchInput || !this.searchButton) {
      return;
    }

    const address = this.searchInput.value;
    if (!address) {
      return;
    }

    this.searchInput.disabled = true;
    this.searchButton.disabled = true;

    const geocoder = new this.google.maps.Geocoder();

    try {
      const results = await new Promise<google.maps.GeocoderResult[]>((resolve, reject) => {
        geocoder.geocode({ address }, (results, status) => {
          if (status === this.google!.maps.GeocoderStatus.OK && results) {
            resolve(results);
          } else {
            reject(new Error(`Geocoding failed: ${status}`));
          }
        });
      });

      if (results.length > 0) {
        this.setPosition(results[0].geometry.location);
        this.emitChange();
      }
    } catch (error) {
      console.error('Search failed:', error); // eslint-disable-line no-console
    } finally {
      this.searchInput.disabled = false;
      this.searchButton.disabled = false;
    }
  }

  private setPosition(latLng: google.maps.LatLng): void {
    if (this.googleMap) {
      this.googleMap.panTo(latLng);
    }
    if (this.googleMapMarker) {
      this.googleMapMarker.setPosition(latLng);
    }

    const lat = Math.round(latLng.lat() * 1000000);
    const lng = Math.round(latLng.lng() * 1000000);

    this.latInput.value = (lat / 1000000).toString();
    this.lngInput.value = (lng / 1000000).toString();

    this.setValue();
  }

  private setValue(): GoogleMapsPicker {
    const lat = parseFloat(this.latInput.value) || this.options.defaultValue?.lat || 0;
    const lng = parseFloat(this.lngInput.value) || this.options.defaultValue?.lng || 0;
    const zoom = parseInt(this.zoomInput.value, 10) || this.options.defaultValue?.zoom || 14;
    const heading = this.headingInput
      ? parseFloat(this.headingInput.value) || this.options.defaultValue?.streetView?.heading || 0
      : 0;
    const pitch = this.pitchInput
      ? parseFloat(this.pitchInput.value) || this.options.defaultValue?.streetView?.pitch || 0
      : 0;
    const zoomView = this.zoomViewInput
      ? parseFloat(this.zoomViewInput.value) || this.options.defaultValue?.streetView?.zoomView || 0
      : 0;
    const type = this.modeSwitcher && this.modeSwitcher.checked ? 'STREETVIEW' : 'ROADMAP';

    const newValue: GoogleMapsValue = {
      type,
      lat,
      lng,
      zoom,
      streetView: { heading, pitch, zoomView },
    };

    this.value = {
      ...this.value,
      ...newValue,
    };

    return this;
  }

  private emitChange(): void {
    if (this.options.onChange) {
      this.options.onChange(this.value);
    }
  }

  getMap(): google.maps.Map | null {
    return this.googleMap;
  }

  destroy(): void {
    // Google Maps APIのイベントリスナーを削除
    this.unregisterMapEvents();
    this.unregisterStreetViewEvents();
    if (this.googleMapMarker) {
      this.googleMapMarker.setMap(null);
    }

    // Vanilla JSイベントリスナーを削除
    for (const [element, listeners] of this.eventRegistry) {
      listeners.forEach(({ type, listener, options }) => {
        element.removeEventListener(type, listener, options);
      });
    }
    this.eventRegistry.clear();

    // プレビューを元に戻す
    const mapDiv = this.googleMap?.getDiv() as HTMLElement | null;
    if (mapDiv) {
      this.mapRoot.removeChild(mapDiv);
    }

    // インスタンス変数をクリア
    this.googleMap = null;
    this.googleMapMarker = null;
    this.google = null;
  }
}
