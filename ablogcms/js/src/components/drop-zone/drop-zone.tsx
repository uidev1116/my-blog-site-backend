import { Component, ReactNode, useCallback, useRef } from 'react';
import classNames from 'classnames';
import readFiles, { type ExtendedFile } from '../../lib/read-files';

interface DropZoneProp {
  children?: ReactNode;
  onComplete: (files: ExtendedFile[]) => void;
}

interface DropZoneState {
  files: File[];
  modifier: string;
  dragging: number;
  reading: boolean;
}

interface DropZoneFileSelectorProps extends React.InputHTMLAttributes<HTMLInputElement> {
  children?: React.ReactNode;
}

export const DropZoneFileSelector = ({
  className,
  children,
  'aria-label': ariaLabel,
  ...props
}: DropZoneFileSelectorProps) => {
  const inputFileRef = useRef<HTMLInputElement>(null);

  const handleClick = useCallback(() => {
    inputFileRef.current?.click();
  }, []);

  return (
    <>
      <button
        type="button"
        className={classNames('acms-admin-drop-zone-file-selector-btn', className)}
        aria-label={ariaLabel}
        onClick={handleClick}
      >
        {children || ACMS.i18n('media.upload')}
      </button>
      <input ref={inputFileRef} type="file" multiple name="files[]" style={{ display: 'none' }} {...props} />
    </>
  );
};

interface DropZoneTextProps extends React.HTMLAttributes<HTMLParagraphElement> {
  children?: React.ReactNode;
}

export const DropZoneText = ({ className, children, ...props }: DropZoneTextProps) => {
  return (
    <p className={classNames('acms-admin-drop-zone-text', className)} {...props}>
      {children}
    </p>
  );
};

export default class DropZone extends Component<DropZoneProp, DropZoneState> {
  constructor(props: DropZoneProp) {
    super(props);
    this.state = {
      files: [],
      modifier: 'drag-n-drop-hover',
      dragging: 0,
      reading: false,
    };
  }

  componentDidMount() {
    setTimeout(() => {
      this.setState({
        modifier: 'drag-n-drop-hover drag-n-drop-fadeout',
      });
    }, 800);
    setTimeout(() => {
      this.setState({
        modifier: '',
      });
    }, 1100);
  }

  onChange(e: React.ChangeEvent<HTMLInputElement>) {
    if (!(e.target.files instanceof FileList)) {
      return;
    }
    this.setState({
      reading: true,
    });
    readFiles(e.target.files).then((files) => {
      const { onComplete } = this.props;
      onComplete(files);
      this.setState({
        reading: false,
      });
    });
  }

  onDrop(e: React.DragEvent<HTMLDivElement>) {
    e.preventDefault();
    e.stopPropagation();

    this.setState({
      modifier: '',
      dragging: 0,
      reading: true,
    });
    readFiles(e.dataTransfer.files).then((files) => {
      const { onComplete } = this.props;
      onComplete(files);
      this.setState({
        reading: false,
      });
    });
    return false;
  }

  onDragOver(e: React.DragEvent<HTMLDivElement>) {
    e.preventDefault();
    e.stopPropagation();
    this.setState({
      modifier: 'drag-n-drop-hover',
    });
    return false;
  }

  onDragEnter(e: React.DragEvent<HTMLDivElement>) {
    e.preventDefault();
    e.stopPropagation();
    const { dragging } = this.state;
    this.setState({
      modifier: 'drag-n-drop-hover',
      dragging: dragging + 1,
    });
    return false;
  }

  onDragLeave(e: React.DragEvent<HTMLDivElement>) {
    e.preventDefault();
    e.stopPropagation();
    let { dragging } = this.state;
    dragging--;
    if (dragging === 0) {
      this.setState({
        modifier: '',
      });
    }
    this.setState({
      dragging,
    });
    return false;
  }

  render() {
    const { children } = this.props;
    const { modifier, reading } = this.state;
    return (
      <div
        className={classNames('acms-admin-drop-zone', modifier)}
        onDragOver={this.onDragOver.bind(this)}
        onDragEnter={this.onDragEnter.bind(this)}
        onDragLeave={this.onDragLeave.bind(this)}
        onDrop={this.onDrop.bind(this)}
      >
        {children || (
          <div className={classNames('acms-admin-drop-box', modifier)}>
            <div className="acms-admin-drop-zone-inside">
              <DropZoneText>{ACMS.i18n('media.add_new_media')}</DropZoneText>
              <DropZoneFileSelector onChange={this.onChange.bind(this)} disabled={reading === true}>
                {ACMS.i18n('media.upload')}
              </DropZoneFileSelector>
              <DropZoneText>{ACMS.i18n('media.drop_file')}</DropZoneText>
            </div>
          </div>
        )}
      </div>
    );
  }
}
