@extends('backend.master')
@push('styles')
    <link rel="stylesheet" href="{{asset('public/backend/css/student_list.css')}}"/>
@endpush


@section('mainContent')

    {!! generateBreadcrumb() !!}

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-lg-6">
                    <div class="main-title">
                        <h3>{{__('student.Import Student')}}</h3>
                    </div>
                </div>
                <div class="offset-lg-2 col-lg-4 text-right mb-20">

                    <a href="{{route('regular_student_excel_download')}}">
                        <button class="primary-btn tr-bg text-uppercase bord-rad">
                            {{__('common.Download')}}
                            <span class="pl ti-download"></span>
                        </button>
                    </a>
                </div>

            </div>

            {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'regular_student_import_save',
                                'method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'student_form']) }}
            <div class="row">
                <div class="col-lg-12">


                    <div class="white-box">
                        <div class="">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="main-title">

                                    </div>
                                </div>
                            </div>


                            <input type="hidden" name="url" id="url" value="{{URL::to('/')}}">
                            <div class="row mb-40 mt-30">


                                <div class="col-lg-12">
                                    <div class="primary_input mb-35">
                                        <label class="primary_input_label"
                                               for="">{{__('common.Browse')}} Excel File<strong
                                                class="text-danger">*</strong> </label>
                                        <div class="primary_file_uploader">
                                            <input class="primary-input" type="text" id="placeholderFileOneName"
                                                   placeholder="{{__('common.Browse')}}  Excel File" readonly="">
                                            <button class="primary_btn_2" type="button">
                                                <label class="primary_btn_2"
                                                       for="document_file_1">{{__('common.Browse')}} </label>
                                                <input type="file" class="d-none" name="file" id="document_file_1">
                                            </button>
                                        </div>
                                    </div>

                                </div>

                            </div>

                            <div class="row mt-40">
                                <div class="col-lg-12 text-center">
                                    <button class="primary-btn fix-gr-bg">
                                        <span class="ti-check"></span>
                                        {{__('student.Import Student')}}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}
        </div>
    </section>

@endsection

