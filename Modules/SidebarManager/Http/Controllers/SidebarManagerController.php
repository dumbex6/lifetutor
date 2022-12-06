<?php

namespace Modules\SidebarManager\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Org\Http\Controllers\ReorderSidebarController;
use Modules\RolePermission\Entities\Permission;
use Modules\SidebarManager\Entities\Backendmenu;
use Modules\SidebarManager\Entities\BackendmenuUser;
use Modules\SidebarManager\Entities\PermissionSection;

class SidebarManagerController extends Controller
{


    private function getMenusData()
    {
        $all_menus = Permission::with('roles', 'assign')
            ->where('type', '!=', 3)
            ->where('backend', 1)->orderBy('position')->get();
        $unused_menus = $all_menus->where('menu_status', 0);
        $used_menu = $all_menus->where('menu_status', 1);
        $query = PermissionSection::query();
        if (!showEcommerce()) {
            $query->where('ecommerce', '!=', 1);
        }
        $sections = $query->with('permissions')->orderBy('position')->get();

        return compact('used_menu', 'unused_menus', 'sections', 'all_menus');
    }

    public function index()
    {
        try {
            $data = $this->getMenusData();
            return view('sidebarmanager::index', $data);
        } catch (\Exception $e) {
            GettingError($e->getMessage(), url()->current(), request()->ip(), request()->userAgent());
        }
    }


    public function sectionStore(Request $request)
    {
        $code = auth()->user()->language_code;
        $rules = [
            'name.' . $code => 'required',
        ];
        $this->validate($request, $rules, validationMessage($rules));

        try {
            $section = new PermissionSection();
            foreach ((array)$request->get('name') as $key => $name) {
                $section->setTranslation('name', $key, $name);
            }
            $section->save();

            return $this->reloadWithData();
        } catch (Exception $e) {
        }
    }

    public function menuStore(Request $request)
    {

        $code = auth()->user()->language_code;

        $rules = [
            'label.' . $code => 'required',
            'route' => 'required',
//            'route' => 'required|unique:permissions',
        ];

        $this->validate($request, $rules, validationMessage($rules));

        if (!routeIsExist($request->route)) {
            $result['errors']['route'] = trans('common.The Route not exist');
            return new JsonResponse($result, 500);
        }
        try {
            $permission = new Permission();
            foreach ((array)$request->get('label') as $key => $name) {
                $permission->setTranslation('name', $key, $name);
            }
            $permission->route = $request->route;
            $permission->type = 1;
            $permission->save();

            return $this->reloadWithData();
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function deleteSection(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != 1) {
                $section = PermissionSection::where('id', $request->id)->first();
                if (!empty($section->permissions)) {
                    foreach ($section->permissions as $permission) {
                        $permission->section_id = 1;
                        $permission->save();
                    }
                }
                $section->delete();
            }
            DB::commit();
            return $this->reloadWithData();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => __('common.Operation failed')
            ], 500);
        }

    }

    public function removeMenu(Request $request)
    {
        if ($request->id) {
            $menu = Permission::find($request->id);
            if ($menu) {
                $menu->menu_status = 0;
                $menu->save();
            }
        }
        return $this->reloadWithData();

    }

    public function menuEdit(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);

        $menu = Permission::find($request->id);
        if (empty($menu->old_name)) {
            $menu->old_name = $menu->name;
        }


        foreach ((array)$request->get('label') as $key => $name) {
            $menu->setTranslation('name', $key, $name);
        }
        $menu->icon = $request->icon;
        $menu->save();

        $data = $this->getMenusData();
        return response()->json([
            'msg' => 'Success',
            'available_list' => (string)view('sidebarmanager::components.available_list', $data),
            'menus' => (string)view('sidebarmanager::components.components', $data),
            'live_preview' => (string)view('sidebarmanager::components.live_preview', $data)
        ], 200);
    }

    public function sectionEdit(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);

        $section = PermissionSection::find($request->id);

        foreach ((array)$request->get('name') as $key => $name) {
            $section->setTranslation('name', $key, $name);
        }

        $section->save();

        $data = $this->getMenusData();
        return response()->json([
            'msg' => 'Success',
            'available_list' => (string)view('sidebarmanager::components.available_list', $data),
            'menus' => (string)view('sidebarmanager::components.components', $data),
            'live_preview' => (string)view('sidebarmanager::components.live_preview', $data)
        ], 200);
    }

    public function menuUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required'
        ]);


        $datas = json_decode($request->ids);;
        $ids = [];
        foreach ($datas as $key => $data) {
            $menu = Permission::where('id', $data->id)->first();
            if ($menu) {
                $ids[] = $data->id;
                $old_type = empty($menu->old_type) ? $menu->type : null;
                if (!empty($menu->old_type) && $menu->old_type == 1) {
                    $old_parent_route = null;
                } else {
                    $old_parent_route = empty($menu->old_parent_route) ? $menu->parent_route : null;
                }

                if (!isset($data->is_sub_menu)) {
                    $menu->update([
                        'old_type' => $old_type,
                        'old_parent_route' => $old_parent_route,
                        'type' => 1,
                        'parent_route' => null,
                        'position' => $key + 1,
                        'menu_status' => 1,
                        'section_id' => $data->section_id ?? 1
                    ]);
                } else {
                    $parent = Permission::where('id', $data->parent_id)->first();
                    if ($parent && $parent->route != 'dashboard') {
                        $parent_route = $parent->route;
                    } else {
                        $parent_route = $menu->parent_route;
                    }
                    if ($parent_route != $menu->route) {
                        $menu->update([
                            'old_type' => $old_type,
                            'old_parent_route' => $old_parent_route,
                            'type' => 2,
                            'parent_route' => $parent_route,
                            'position' => $key + 1,
                            'menu_status' => 1,
                            'section_id' => $data->section_id ?? 1
                        ]);
                    }

                }
            }
        }

//        Permission::whereNotIn('id', $ids)->update([
//            'menu_status' => 0
//        ]);

        return $this->reloadWithData();
    }


    public function sortSection(Request $request)
    {
        $request->validate([
            'ids' => 'required'
        ]);
        foreach ($request->ids as $key => $id) {
            $section = PermissionSection::where('id', $id)->first();
            if ($section) {
                $section->update([
                    'position' => $key + 1
                ]);
            }
        }
        return $this->reloadWithData();
    }

    public function resetMenu(Request $request)
    {
        DB::beginTransaction();
        try {
            PermissionSection::where('id', '!=', 1)->delete();
            $permissions = Permission::all();
            foreach ($permissions as $permission) {
                if (!empty($permission->old_name)) {
                    $permission->name = $permission->old_name;
                }
                if (!empty($permission->old_type)) {
                    $permission->type = $permission->old_type;
                }
                if (!empty($permission->old_parent_route)) {
                    $permission->parent_route = $permission->old_parent_route;
                }
                switch ($permission->route){
                    case "students":
                        $permission->icon = 'fas fa-user';
                        break;
                    case "courses":
                        $permission->icon = 'fas fa-book';
                        break;
                    case "quiz":
                        $permission->icon = 'fas fa-question-circle';
                        break;
                    case "reports":
                        $permission->icon = 'fas fa-chart-area';
                        break;
                    case "communications":
                        $permission->icon = 'fas fa-comments';
                        break;
                    case "settings":
                        $permission->icon = 'fas fa-cogs';
                        break;
                    case "frontend_CMS":
                        $permission->icon = 'fas fa-paint-roller';
                        break;
                    case "certificate":
                        $permission->icon = 'fas fa-certificate';
                        break;
                    case "virtual-class":
                        $permission->icon = 'fas fa-vr-cardboard';
                        break;
                    case "utility":
                        $permission->icon = 'fas fa-hammer';
                        break;
                    case "org-subscription":
                        $permission->icon = 'fas fa-chalkboard';
                        break;
                    case "offline-manage":
                        $permission->icon = 'fas fa-person-booth';
                        break;
                    case "survey":
                        $permission->icon = 'fas fa-poll-h';
                        break;
                    default:
                        $permission->icon = 'fas fa-th';
                }
                $permission->menu_status = 1;
                $permission->section_id = 1;
                $permission->position = 9999999;
                $permission->save();
            }

            if (isModuleActive('Org')) {
                $reorder = new ReorderSidebarController();
                $reorder->order();
            }

            DB::commit();
            return response()->json([
                'msg' => 'Success'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Failed'
            ], 500);
        }

    }

    private function reloadWithData()
    {


        $data = $this->getMenusData();
        return response()->json([
            'msg' => 'Success',
            'available_list' => (string)view('sidebarmanager::components.available_list', $data),
            'menus' => (string)view('sidebarmanager::components.components', $data),
            'live_preview' => (string)view('sidebarmanager::components.live_preview', $data)
        ], 200);
    }

    public function menuEditForm($id)
    {
        $menu = Permission::findOrFail($id);
        return view('sidebarmanager::components.edit_modal', compact('menu'));
    }

    public function sectionEditForm($id)
    {
        $section = PermissionSection::findOrFail($id);
        return view('sidebarmanager::components.edit_modal_section', compact('section'));
    }


}
