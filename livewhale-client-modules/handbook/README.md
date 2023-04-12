This module generates navigation for handbook pages, as well as adds the corresponding anchor links to headers in the main content area. It is designed to allow for multiple handbooks in different LiveWhale CMS groups.

To use:
- Create a "Handbook" Page template that includes a <xphp var="table_of_contents"/> in the sidebar.
- Update handbook_path config to point to that file
- Update element_id to indicate the main editable area <div id="main-content-area" class="editable"> where you'll be putting your handbook text

When using the template:
- Each h2 or h3 (or h4/h5, see user options) in your main editable area will be added in the Table of Contents (TOC) as an anchor link.
- If it detects an anchor link has already been added or pasted in for some header, it will try to use that instead.

User options:
- By default, h2 and h3s are included in the TOC. If you add the page tag "Include H4" or "Include H5", those header levels will be added to the TOC.
- By default, each link in the TOC will match its corresponding header text exactly. To override this on a per-header basis, use <h2 data-title="Short Name for Nav">Much Longer Name That We Don't Want in the Nav</h2>
- This is designed to work across multiple sibling or parent/child pages that all use the Handbook template. Therefore, you can split your handbook across multiple pages, but the TOC nav will link between them seamlessly, going by the page order in the nav. However, you can also use a page tag "Single Page Handbook" to bypass this nav-scanning. When that tag is used on the page, the TOC will only show anchor links on the current page.