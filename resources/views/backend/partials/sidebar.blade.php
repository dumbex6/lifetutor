<!-- sidebar part here -->
<nav id="sidebar" class="sidebar ">

    <div class="sidebar-header update_sidebar">
        <a class="large_logo" href="{{ url('/') }}">
            <img src="{{ getLogoImage(Settings('logo')) }}" alt="">
        </a>
        <a class="mini_logo" href="{{ url('/') }}">
            <img src="{{ getLogoImage(Settings('logo')) }}" alt="">
        </a>
        <a id="close_sidebar" class="d-lg-none">
            <i class="ti-close"></i>
        </a>
    </div>
    <ul id="sidebar_menu">

        @if ((isModuleActive('LmsSaas') || isModuleActive('LmsSaasMD')) && SaasDomain() != 'main' && !hasActiveSaasPlan())
            <li>
                <a href="#" class="has-arrow" aria-expanded="false">
                    <div class="nav_icon_small">
                        <span class="fas fa-university"></span>
                    </div>
                    <div class="nav_title">
                        <span>{{ __('saas.Saas Management') }}</span>
                    </div>
                </a>

                <ul>
                    <li>
                        <a href="{{ route('saas.myPlan') }}">{{ __('saas.My Plan') }}</a>
                    </li>
                </ul>
            </li>
        @else
            @foreach($sections as $key => $section)
                @if(!empty($section->name))
                    <span class="menu_seperator">
                    {{$section->name}}
                </span>
                @endif
                @if($section->permissions->count())
                    @foreach($section->activeMenus as  $menu)
                        @php
                            $submenus =$section->activeSubmenus->where('parent_route',$menu->route)->where('parent_route','!=','dashboard');
                            if(hasDynamicPage()){
                                $ignoreDynamicPage=[
                                    'frontend.homeContent','frontend.privacy_policy','frontend.privacy_policy','frontend.AboutPage','frontend.ContactPageContent','frontend.pageContent'
                            ];
                               $submenus =   $submenus->whereNotIn('route',$ignoreDynamicPage);
                            }
                        @endphp
                        @if(permissionCheck($menu->route))

                            @if(!$menu->module ||  isModuleActive($menu->module))
                                @php
                                    $hasChild =$submenus->count();
                                @endphp
                                <li class="{{spn_active_link(childrenRoute($menu))}}">
                                    <a href="@if(!$hasChild && validRouteUrl($menu->route)) {{validRouteUrl($menu->route)}} @else # @endif"
                                       class=" @if($hasChild) has-arrow @endif"
                                       aria-expanded="false">
                                        <div class="nav_icon_small">
                                            <span class="{{@$menu->icon??'fas fa-th'}}"></span>
                                        </div>
                                        <div class="nav_title">
                                            <span>{{$menu->name}}</span>
                                            @if(env('APP_SYNC') && !empty($menu->module))
                                                <span class="demo_addons">Addon</span>
                                            @endif
                                        </div>
                                    </a>
                                    @if($hasChild)
                                        <ul>
                                            @foreach($submenus as $submenu)
                                                @if(permissionCheck($submenu->route))
                                                    @if(!$submenu->module ||  isModuleActive($submenu->module))
                                                        <li class="{{spn_active_link(childrenRoute($submenu))}}">
                                                            <a href="@if(!empty(validRouteUrl($submenu->route))) {{validRouteUrl($submenu->route)}} @else # @endif"> {{$submenu->name}}</a>
                                                        </li>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endif
                        @endif

                    @endforeach
                @endif
            @endforeach

        @endif
    </ul>

</nav>

