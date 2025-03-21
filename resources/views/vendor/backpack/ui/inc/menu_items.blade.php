{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Users" icon="la la-question" :link="backpack_url('user')" />
<x-backpack::menu-item title="Customers" icon="la la-question" :link="backpack_url('customers')" />
<x-backpack::menu-item title="Otps" icon="la la-question" :link="backpack_url('otp')" />
<x-backpack::menu-item title="Products" icon="la la-question" :link="backpack_url('products')" />
<x-backpack::menu-item title="Categories" icon="la la-question" :link="backpack_url('categories')" />
<x-backpack::menu-item title="Scan results" icon="la la-question" :link="backpack_url('scan-results')" />
<x-backpack::menu-item title='Pages' icon='la la-file-o' :link="backpack_url('page')" />
<x-backpack::menu-item :title="trans('backpack::crud.file_manager')" icon="la la-files-o" :link="backpack_url('elfinder')" />
<x-backpack::menu-item title="Bug reports" icon="la la-question" :link="backpack_url('bug-reports')" />
<x-backpack::menu-item title="Contact us" icon="la la-question" :link="backpack_url('contact-us')" />
<x-backpack::menu-item title="Packages" icon="la la-question" :link="backpack_url('packages')" />
<x-backpack::menu-item title="Customer packages" icon="la la-question" :link="backpack_url('customer-packages')" />
<x-backpack::menu-item title="Customer favorites" icon="la la-question" :link="backpack_url('customer-favorites')" />
<x-backpack::menu-item title="Countries" icon="la la-question" :link="backpack_url('countries')" />