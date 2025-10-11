import { Component } from 'react';
import { ClientRect, DndContext, DragEndEvent, Modifier, getClientRect, useDraggable } from '@dnd-kit/core';
import { CSS, Transform } from '@dnd-kit/utilities';
import Cropper from 'cropperjs';
import classnames from 'classnames';
import styled from 'styled-components';

import DropZone from '../../../../components/drop-zone/drop-zone';
import Modal from '../../../../components/modal/modal';
import 'cropperjs/dist/cropper.css';
import rotateIcon from '../../assets/images/media-rotate-icon.svg';
import focalPointIcon from '../../assets/images/media-focal-point.svg';
import cropIcon from '../../assets/images/media-crop-icon.svg';
import focalUI from '../../assets/images/media-crop-focal.svg';
import { ExtendedFile } from '../../../../lib/read-files';
import { calcHeightFromRatio, calcVhToPx, calcWidthFromRatio } from '../../../../utils';
import { FocalPoint } from '../../types';
import { coordinatesToTransform, focalPointToCoordinates } from '../../utils';
import HStack from '../../../../components/stack/h-stack';

/**
 * 画像の最大高を指定
 */
const MAX_HEIGHT_VH = 55;

const StyledModal = styled(Modal)`
  .acms-admin-modal-backdrop {
    background-color: rgb(0 0 0);
  }

  .acms-admin-modal-body {
    padding: 0;
    margin: 0;
    background-color: #1a2029;
  }

  .acms-admin-modal-content {
    padding-right: 0;
    padding-left: 0;
  }

  .acms-admin-cropper-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 10px 40px;

    > * + * {
      margin-top: 10px !important;
    }
  }

  .acms-admin-cropper-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: ${MAX_HEIGHT_VH}vh;
  }

  .acms-admin-cropper-img-container {
    position: relative;
    width: 100%;
    max-width: 100%;
  }

  .acms-admin-cropper-img-container img {
    max-height: ${MAX_HEIGHT_VH}vh;
  }

  .acms-admin-cropper-tools,
  .acms-admin-cropper-input-wrap {
    box-sizing: border-box;
    width: 130px;
    padding: 0 15px;
  }

  .acms-admin-cropper-input {
    padding: 7px 10px;
    margin-bottom: 5px;
    font-size: 14px;
    color: #fff;
    background-color: #2f3641;
    border: none;
    border-radius: 3px;
  }

  .acms-admin-cropper-input-label {
    display: inline-block;
    font-size: 10px;
    color: #fff;
  }

  .acms-admin-cropper-icon {
    display: block;
    width: auto;
    height: 30px;
    margin: 0 auto 5px;
  }

  .acms-admin-cropper-icon-crop {
    display: block;
    width: 40px;
    height: auto;
    margin: 0 auto 5px;
  }

  .acms-admin-cropper-reflect-group {
    display: table;
    width: 100%;
    border: 1px solid #333a45;
    border-radius: 3px;
  }

  .acms-admin-cropper-reflect-btn {
    display: table-cell;
    width: 50%;
    padding: 10px 15px;
    color: #fff;
    background-color: transparent;
    border: none;
  }

  .acms-admin-cropper-reflect-icon {
    display: block;
    width: 20px;
    height: auto;
    margin: 0 auto 5px;
  }

  .acms-admin-cropper-reflect-label {
    margin-bottom: 30px;
    font-size: 10px;
    color: #fff;
    text-align: center;
  }

  .acms-admin-cropper-btn-group-wrap {
    flex: 1;
  }

  .acms-admin-cropper-btn-group {
    display: table;
  }

  .acms-admin-cropper-btn-label {
    display: table-cell;
    padding-right: 20px;
    font-size: 12px;
    color: #fff;
    vertical-align: middle;
  }

  .acms-admin-cropper-group {
    display: flex;
  }

  .acms-admin-cropper-btn {
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 65px;
    padding: 10px;
    font-size: 12px;
    color: #fff;
    vertical-align: middle;
    background-color: transparent;
    border: none;
    border-radius: 3px;
    transition: background 0.3s;

    &.active,
    &:hover {
      background-color: #232b36;
      border-radius: 3px;
    }
  }

  .acms-admin-cropper-btn-txt {
    @media (width <= 767px) {
      display: none;
    }
  }

  .acms-admin-cropper-crop-btn-block {
    display: block;
  }

  .cropper-modal {
    background-color: #1a2029;
  }

  .acms-admin-focal-point-btn {
    position: absolute;
    display: inline-block;
    width: 45px;
    height: 45px;
    padding: 0;
    margin-top: -22.5px; /* 要素の高さの半分を引く */
    margin-left: -22.5px; /* 要素の幅の半分を引く */
    cursor: grab;
    background-color: transparent;
    border: none;
  }

  .acms-admin-cropper-focal-point {
    display: inline-block;
    width: 100%;
    height: 100%;
    background-image: url(${focalUI});
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
  }

  .acms-admin-cropper-bottom {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0 30px;
    margin: 0;
    background-color: #303945;
  }

  .cropper-bg {
    max-width: 100%;
    background: transparent !important;
  }

  .cropper-face {
    opacity: 0;
  }

  .cropper-dashed {
    border: 1px solid #fff;
  }

  .cropper-point {
    width: 10px;
    height: 10px;
    background-color: #fff;
    border-radius: 50%;

    &.point-n {
      top: -5px;
      margin-left: -5px;
    }

    &.point-e {
      right: -5px;
      margin-top: -5px;
    }

    &.point-w {
      left: -5px;
      margin-top: -5px;
    }

    &.point-s {
      bottom: -5px;
      margin-left: -5px;
    }

    &.point-ne {
      top: -5px;
      right: -5px;
    }

    &.point-nw {
      top: -5px;
      left: -5px;
    }

    &.point-sw {
      bottom: -5px;
      left: -5px;
    }

    &.point-se {
      right: -5px;
      bottom: -5px;
    }
  }

  .cropper-line {
    background-color: #fff;
  }

  .cropper-view-box {
    outline: 1px solid #fff;
    outline-color: #fff;
  }

  .acms-admin-cropper-crop-list-wrap {
    position: relative;
  }

  .acms-admin-cropper-crop-list {
    position: absolute;
    bottom: 69px;
    left: 0;
    width: 80px;
    text-align: center;
    background-color: #3f4a58;

    &::after {
      position: absolute;
      bottom: -10px;
      left: 30px;
      display: block;
      width: 1px;
      height: 0;
      content: ' ';
      border-color: #3f4a58 transparent transparent;
      border-style: solid;
      border-width: 10px 10px 0;
    }

    @media (width <= 767px) {
      left: 0;
      width: 120px;

      &::after {
        left: 50px;
      }
    }
  }

  .acms-admin-cropper-list-btn {
    width: 100%;
    padding: 8px 0;
    font-size: 12px;
    color: #fff;
    background-color: transparent;
    border: none;
    border-bottom: 1px solid #303945;

    &:hover,
    &.active {
      background-color: #232b36;
    }

    &:last-child {
      border-bottom: none;
    }

    @media (width <= 767px) {
      font-size: 16px;
      line-height: 45px;
    }
  }

  .acms-admin-cropper-crop-info {
    text-align: center;
  }

  .acms-admin-cropper-crop-info-img {
    width: 18px;
    height: auto;
    margin-right: 10px;
    vertical-align: middle;
  }

  .acms-admin-cropper-crop-info-text {
    display: inline-block;
    margin-right: 5px;
    margin-left: 5px;
    font-size: 14px;
    color: #fff;
    vertical-align: middle;
  }

  .acms-admin-cropper-crop-info-input {
    display: inline-block;
    width: 100px;
    padding: 0 4px;
    font-size: 16px;
    line-height: 22px;
    color: #fff;
    vertical-align: middle;
    background-color: #090c12;
    border: none;
  }

  .acms-admin-cropper-original-btn {
    margin-right: 5px;
    font-size: 14px;
    color: #006dec;
    appearance: none;
    background: transparent;
    border: none;
  }
`;

interface MediaEditModalProps {
  onClose: () => void;
  onCrop: (blob: Blob | null, focalPoint: FocalPoint) => void;
  isOpen: boolean;
  src: string;
  focalPoint: FocalPoint;
  mediaCropSizes: [number, number][];
  title?: string;
  width: number;
  height: number;
  original?: string;
  container?: HTMLElement;
}

interface MediaEditModalState {
  upload: boolean;
  blob?: Blob | null;
  cropWidth: number;
  cropHeight: number;
  useFocalPoint: boolean;
  focalPoint: FocalPoint;
  zoom: number;
  ratio: number;
  src: string;
  timeout: NodeJS.Timeout | null;
  isCropListOpen: boolean;
}

/**
 * ドラッグ可能な要素の可動域を指定された範囲内に制限する
 * @see https://github.com/clauderic/dnd-kit/blob/master/packages/modifiers/src/utilities/restrictToBoundingRect.ts
 */
function restrictToBoundingRect(transform: Transform, rect: ClientRect, boundingRect: ClientRect): Transform {
  const value = {
    ...transform,
  };
  if (rect.top + transform.y <= boundingRect.top) {
    value.y = boundingRect.top - rect.top;
  } else if (rect.bottom + transform.y >= boundingRect.top + boundingRect.height) {
    value.y = boundingRect.top + boundingRect.height - rect.bottom;
  }
  if (rect.left + transform.x <= boundingRect.left) {
    value.x = boundingRect.left - rect.left;
  } else if (rect.right + transform.x >= boundingRect.left + boundingRect.width) {
    value.x = boundingRect.left + boundingRect.width - rect.right;
  }
  return value;
}

interface DraggableFocalPointProps {
  children: React.ReactNode;
  focalPointTransform: Transform;
}

const DraggableFocalPoint = ({ children, focalPointTransform }: DraggableFocalPointProps) => {
  const { attributes, listeners, setNodeRef, transform } = useDraggable({
    id: 'focal-point',
  });

  const style = {
    transform: CSS.Translate.toString(transform),
    position: 'absolute' as const,
    left: `calc(50% + ${focalPointTransform.x}px)`,
    top: `calc(50% + ${focalPointTransform.y}px)`,
  };

  return (
    <button
      type="button"
      ref={setNodeRef}
      className="acms-admin-focal-point-btn"
      style={style}
      {...listeners}
      {...attributes}
      aria-label={ACMS.i18n('media.focal_point_button_label')}
    >
      {children}
    </button>
  );
};

export default class MediaEditModal extends Component<MediaEditModalProps, MediaEditModalState> {
  image: HTMLImageElement | null;

  cropper: Cropper | null;

  constructor(props: MediaEditModalProps) {
    super(props);
    this.state = {
      blob: null,
      upload: false,
      cropWidth: 0,
      cropHeight: 0,
      zoom: 1,
      ratio: 0,
      src: '',
      useFocalPoint: false,
      focalPoint: props.focalPoint,
      timeout: null,
      isCropListOpen: false,
    };
    this.cropper = null;
    this.image = null;
  }

  componentWillUnmount(): void {
    if (this.cropper) {
      this.cropper.destroy();
    }
  }

  setImageRef(node: HTMLImageElement | null) {
    this.image = node;
    if (node !== null && this.cropper === null) {
      this.cropper = new Cropper(node, {
        autoCrop: false,
        zoomable: false,
        responsive: true,
        viewMode: 2,
        crop: (e) => {
          const data = e.detail;
          if (data.width || data.height) {
            this.setState({
              cropWidth: data.width,
              cropHeight: data.height,
            });
          } else {
            this.setState({
              cropWidth: 0,
              cropHeight: 0,
            });
          }
        },
        autoCropArea: 1,
        dragMode: 'none' as Cropper.DragMode,
      });
    }
  }

  handleAfterOpen() {
    const { focalPoint } = this.props;
    this.setState({
      focalPoint, // モーダルが開いた時に、親コンポーネントから渡された focalPoint を state にセット
    });
  }

  handleAfterClose() {
    if (this.cropper) {
      this.cropper.destroy();
      this.cropper = null;
    }
    this.setState({
      cropWidth: 0,
      cropHeight: 0,
      ratio: 0,
      useFocalPoint: false,
    });
  }

  onCancel() {
    this.setState({
      cropWidth: 0,
      cropHeight: 0,
      ratio: 0,
      useFocalPoint: false,
      focalPoint: this.props.focalPoint,
    });
    this.props.onClose();
  }

  onCrop() {
    if (!this.image) {
      return;
    }
    if (!this.cropper) {
      return;
    }
    let mimeType = 'image/png';
    if (this.image.src) {
      const lastIndex = this.image.src.lastIndexOf('?');
      const src = lastIndex === -1 ? this.image.src : this.image.src.substring(0, lastIndex);
      if (src.slice(-3) !== 'png') {
        mimeType = 'image/jpeg';
      }
    }
    this.cropper.getCroppedCanvas().toBlob((blob) => {
      const { focalPoint } = this.state;
      this.props.onCrop(blob, focalPoint);
    }, mimeType);
  }

  onUploadImg(files: ExtendedFile[]) {
    if (!files.length) {
      return;
    }
    if (files[0].filetype === 'image') {
      this.cropper?.replace(files[0].preview);
    }
  }

  useOriginalImg() {
    if (this.props.original) {
      this.cropper?.replace(this.props.original);
    }
  }

  changeAspectRatio(ratioProp: number) {
    if (!this.cropper) {
      return;
    }
    const { ratio } = this.state;
    this.cropper.clear();

    if (ratioProp === ratio || (isNaN(ratioProp) && isNaN(ratio))) {
      this.setState({
        ratio: 0,
      });
      return;
    }
    this.cropper.setAspectRatio(ratioProp);
    this.cropper.crop();
    this.setState({
      ratio: ratioProp,
    });
  }

  rotate(rotate: number) {
    if (!this.cropper) {
      return;
    }
    const data = this.cropper.getCropBoxData();
    const contData = this.cropper.getContainerData();
    data.width = 2;
    data.height = 2;
    data.top = 0;
    const leftNew = contData.width / 2 - 1;
    data.left = leftNew;
    this.cropper.setCropBoxData(data);
    this.cropper.rotate(rotate);
    const canvData = this.cropper.getCanvasData();
    const heightOld = canvData.height;
    const heightNew = heightOld > contData.height ? contData.height : heightOld;
    const koef = heightNew / heightOld;
    const widthNew = canvData.width * koef;
    canvData.height = heightNew;
    canvData.width = widthNew;
    canvData.top = 0;
    if (canvData.width >= contData.width) {
      canvData.left = 0;
    } else {
      canvData.left = (contData.width - canvData.width) / 2;
    }
    if (canvData.height >= contData.height) {
      canvData.top = 0;
    } else {
      canvData.top = (contData.height - canvData.height) / 2;
    }
    this.cropper.setCanvasData(canvData);
    data.left = 0;
    data.top = 0;
    data.width = canvData.width;
    data.height = canvData.height;
    this.cropper.setCropBoxData(data);
  }

  toggleFocalPoint() {
    const { useFocalPoint } = this.state;
    this.setState({
      useFocalPoint: !useFocalPoint,
    });
  }

  handleFocalDragEnd = (event: DragEndEvent) => {
    if (!this.cropper) {
      return;
    }
    const { delta } = event;

    const canvas = this.cropper.getCanvasData();
    const { width, height } = canvas;
    const { focalPoint } = this.state;
    const focalPointCoordinates = focalPointToCoordinates(focalPoint, canvas);

    const x = ((focalPointCoordinates.x + delta.x) / width) * 100;
    const y = ((focalPointCoordinates.y + delta.y) / height) * 100;

    // Clamp values between 0 and 100 to keep within bounds
    const percentX = Math.max(0, Math.min(100, x));
    const percentY = Math.max(0, Math.min(100, y));

    this.setState({
      focalPoint: [percentX, percentY],
    });
  };

  focalPointToTransform = (focalPoint: FocalPoint): Transform => {
    if (!this.cropper) {
      return { x: 0, y: 0, scaleX: 1, scaleY: 1 };
    }
    const canvas = this.cropper.getCanvasData();
    const coordinates = focalPointToCoordinates(focalPoint, canvas);
    const transform = coordinatesToTransform(coordinates, canvas);
    return transform;
  };

  hoverOnCropList = () => {
    if (this.state.timeout) {
      clearTimeout(this.state.timeout);
    }
    this.setState({
      isCropListOpen: true,
    });
  };

  leaveFromCropList = () => {
    const timeout = setTimeout(() => {
      this.setState({
        isCropListOpen: false,
      });
    }, 500);
    this.setState({
      timeout,
    });
  };

  toggleCropList = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    const { isCropListOpen } = this.state;
    this.setState({
      isCropListOpen: !isCropListOpen,
    });
  };

  restrictToCanvas: Modifier = ({ draggingNodeRect, transform }) => {
    if (!this.cropper) {
      return transform;
    }
    if (!draggingNodeRect) {
      return transform;
    }

    // @ts-expect-error Cropper の型定義には canvas が存在しないが、実際には存在する
    const canvasRect = getClientRect(this.cropper.canvas);

    // canvasRect をカスタマイズして、ボタンが動ける範囲を制限
    const width = canvasRect.width + draggingNodeRect.width;
    const height = canvasRect.height + draggingNodeRect.height;
    const left = canvasRect.left - draggingNodeRect.width / 2;
    const right = canvasRect.right + draggingNodeRect.width / 2;
    const top = canvasRect.top - draggingNodeRect.height / 2;
    const bottom = canvasRect.bottom + draggingNodeRect.height / 2;

    const restrictedCanvasRect: ClientRect = {
      width,
      height,
      left,
      right,
      top,
      bottom,
    };

    return restrictToBoundingRect(transform, draggingNodeRect, restrictedCanvasRect);
  };

  getImgWidth(width: number, height: number) {
    const ratio = width / height;
    const heightVh = calcVhToPx(MAX_HEIGHT_VH);

    // 画像の高さが指定した高さを超える場合は、高さを指定した高さに合わせて幅を計算
    const imageWidth = calcWidthFromRatio(ratio, Math.min(heightVh, height));
    const imageHeight = calcHeightFromRatio(ratio, imageWidth);

    return `${Math.max(imageWidth, imageHeight)}px`;
  }

  render() {
    const { cropWidth, cropHeight, ratio, useFocalPoint, isCropListOpen, focalPoint } = this.state;
    const { src, original, mediaCropSizes, title, width, height, isOpen, container } = this.props;

    return (
      <StyledModal
        isOpen={isOpen}
        onClose={this.onCancel.bind(this)}
        size="large"
        container={container}
        onAfterOpen={this.handleAfterOpen.bind(this)}
        onAfterClose={this.handleAfterClose.bind(this)}
        aria-labelledby="acms-media-edit-modal-title"
      >
        <StyledModal.Header>{`${ACMS.i18n('media.edit_image')}${title ? ` - ${title}` : ''}`}</StyledModal.Header>
        <StyledModal.Body>
          <div>
            <DropZone onComplete={this.onUploadImg.bind(this)}>
              <div>
                <div className="acms-admin-cropper-container">
                  {cropWidth > 0 && cropHeight > 0 && (
                    <div className="acms-admin-cropper-crop-info">
                      <img src={cropIcon} className="acms-admin-cropper-crop-info-img" alt="" />
                      <input type="text" className="acms-admin-cropper-crop-info-input" value={cropWidth} readOnly />
                      <span className="acms-admin-cropper-crop-info-text">×</span>
                      <input type="text" className="acms-admin-cropper-crop-info-input" value={cropHeight} readOnly />
                    </div>
                  )}
                  <div className="acms-admin-cropper-wrap">
                    <div
                      className="acms-admin-cropper-img-container"
                      // windowリサイズ時に画像が必要以上に大きくならないようにするため、maxWidth を指定
                      style={{ maxWidth: this.getImgWidth(width, height) }}
                    >
                      <img src={src} ref={this.setImageRef.bind(this)} className="acms-admin-img-responsive" alt="" />
                      {useFocalPoint && (
                        <DndContext onDragEnd={this.handleFocalDragEnd.bind(this)} modifiers={[this.restrictToCanvas]}>
                          <DraggableFocalPoint focalPointTransform={this.focalPointToTransform(focalPoint)}>
                            <span className="acms-admin-cropper-focal-point" aria-hidden />
                          </DraggableFocalPoint>
                        </DndContext>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </DropZone>
            <div className="acms-admin-form acms-admin-cropper-bottom">
              <div className="acms-admin-cropper-group-wrap">
                <div className="acms-admin-cropper-group">
                  <div className="acms-admin-cropper-crop-list-wrap">
                    <button
                      type="button"
                      className="acms-admin-cropper-btn"
                      onMouseOver={this.hoverOnCropList}
                      onMouseLeave={this.leaveFromCropList}
                      onFocus={this.hoverOnCropList}
                      onBlur={this.leaveFromCropList}
                      onClick={this.toggleCropList}
                    >
                      <img src={cropIcon} className="acms-admin-cropper-icon" alt="" />
                      <span className="acms-admin-cropper-btn-txt">{ACMS.i18n('media.crop')}</span>
                    </button>
                    {isCropListOpen && (
                      <div
                        className="acms-admin-cropper-crop-list"
                        onMouseOver={this.hoverOnCropList}
                        onMouseLeave={this.leaveFromCropList}
                        onFocus={this.hoverOnCropList}
                        onBlur={this.leaveFromCropList}
                      >
                        <button
                          type="button"
                          className={classnames('acms-admin-cropper-list-btn', {
                            active: isNaN(ratio),
                          })}
                          onClick={(e) => {
                            e.preventDefault();
                            this.changeAspectRatio(NaN);
                            this.setState({
                              isCropListOpen: false,
                            });
                          }}
                        >
                          {ACMS.i18n('media.custom')}
                        </button>
                        {mediaCropSizes.map((size) => (
                          <button
                            key={size.join('x')}
                            type="button"
                            className={classnames('acms-admin-cropper-list-btn', {
                              active: ratio === size[0] / size[1],
                            })}
                            onClick={(e) => {
                              e.preventDefault();
                              this.changeAspectRatio(size[0] / size[1]);
                              this.setState({
                                isCropListOpen: false,
                              });
                            }}
                          >
                            {`${size[0]} / ${size[1]}`}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                  <button
                    type="button"
                    className="acms-admin-cropper-btn"
                    onClick={(e) => {
                      e.preventDefault();
                      this.rotate(90);
                    }}
                  >
                    <img src={rotateIcon} className="acms-admin-cropper-icon" alt="" />
                    <span className="acms-admin-cropper-btn-txt">{ACMS.i18n('media.rotate')}</span>
                  </button>
                  <button
                    type="button"
                    className={classnames('acms-admin-cropper-btn', {
                      active: useFocalPoint,
                    })}
                    onClick={(e) => {
                      e.preventDefault();
                      this.toggleFocalPoint();
                    }}
                  >
                    <img src={focalPointIcon} className="acms-admin-cropper-icon" alt="" />
                    <span className="acms-admin-cropper-btn-txt">{ACMS.i18n('media.focal_point')}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </StyledModal.Body>
        <StyledModal.Footer>
          <HStack display="inline-flex">
            {original && (
              <button
                type="button"
                className="acms-admin-btn acms-admin-btn-text"
                onClick={this.useOriginalImg.bind(this)}
              >
                {ACMS.i18n('media.initialize')}
              </button>
            )}
            <button type="button" className="acms-admin-btn" onClick={this.onCancel.bind(this)}>
              {ACMS.i18n('media.cancel')}
            </button>
            <button type="button" className="acms-admin-btn acms-admin-btn-info" onClick={this.onCrop.bind(this)}>
              {ACMS.i18n('media.apply')}
            </button>
          </HStack>
        </StyledModal.Footer>
      </StyledModal>
    );
  }
}
