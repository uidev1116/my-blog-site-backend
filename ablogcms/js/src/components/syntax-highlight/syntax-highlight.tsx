import classnames from 'classnames';
import useSyntaxHighlight, { UseSyntaxHighlightOptions } from '../../hooks/use-syntax-highlight';
import './atom-one-dark.css';

interface SyntaxHighlightProps
  extends React.HTMLAttributes<HTMLPreElement>,
    Pick<UseSyntaxHighlightOptions, 'language'> {
  options?: Partial<UseSyntaxHighlightOptions>;
  children: string;
}

const SyntaxHighlight = ({ children, language: languageProp, options, ...props }: SyntaxHighlightProps) => {
  const { value = '', language } = useSyntaxHighlight(children, { language: languageProp, ...options });

  return (
    <pre {...props}>
      <code
        className={classnames(language, 'acms-admin-hljs')}
        // eslint-disable-next-line react/no-danger
        dangerouslySetInnerHTML={{ __html: value.replace(/class="hljs-/g, 'class="acms-admin-hljs-') }}
      />
    </pre>
  );
};

export default SyntaxHighlight;
