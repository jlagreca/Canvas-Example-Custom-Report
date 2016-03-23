<?php
    ini_set("display_errors", 1);
    set_time_limit(0);
    ini_set('auto_detect_line_endings', TRUE);
    
    
    $directory = 'reports';
    $cachedirectory = 'cache';
    $dirMode = 0777;
    $BaseURL = ''; // enter the subdomain for your canvas here
    $resultlimit = '20';
   // $numberofpages = '9';


if (!file_exists($cachedirectory)) {
    mkdir($cachedirectory, $dirMode, true);
}

      if (!file_exists($directory)) {
            mkdir($directory, $dirMode, true);
            }
            if (!file_exists($directory.'/'.'enrollments.csv'))
            {
            $outputcompleted = fopen($directory.'/'.'enrollments.csv','w+');
            $outputcompletedteachers = fopen($directory.'/'.'teachers.csv','w+');
            //$outputcompletedquizzes = fopen($directory.'/'.'quizresponses.csv','w+');
            }
            else 
            {
            $outputcompleted = fopen($directory.'/'.'enrollments.csv','a');
            $outputcompletedteachers = fopen($directory.'/'.'teachers.csv','a');
           // $outputcompletedquizzes = fopen($directory.'/'.'quizresponses.csv','a');
            }
//lets add some headers for the CSVs
            fputcsv($outputcompleted,array('name','role','timezone','last_activity','user_id','course_name','course_section_name','course_section_id', 'enrollment_start_date','enrollment_end_date','current_score','final_score','total_time_in_course','enrollment_state','course_subaccount_id'));
            fputcsv($outputcompletedteachers,array('name','role','timezone','last_activity','user_id','course_id','course_name','course_short_name','term_id','term_name','term_status','course_status','course_section_name','course_section_id', 'enrollment_start_date','enrollment_end_date','total_time_in_course','enrollment_state','course_subaccount_id'));
           // fputcsv($outputcompletedquizzes,array('email','enrollments','something','',''));




//this is super resource intensive so lets write a function to cache stuff

function getJson($url) {
    // cache files are created like cache/abcdef123456...
    date_default_timezone_set('Australia/Sydney');
	$headers = stream_context_create(array(
    'http' => array(
        'method' => 'GET',
        'header' => "Authorization: Bearer INSERT TOKEN HERE"
        )
    ));

    $cacheFile = 'cache' . DIRECTORY_SEPARATOR . md5($url);
    if (file_exists($cacheFile)) {
        $fh = fopen($cacheFile, 'r');
        $cacheTime = trim(fgets($fh));

        // if data was cached recently, return cached data
        if ($cacheTime > strtotime('-60 minutes')) {
            return fread($fh, filesize($cacheFile));
        }

        // else delete cache file
        fclose($fh);
        unlink($cacheFile);
    }

    $json = file_get_contents($url, false, $headers);

    $fh = fopen($cacheFile, 'w');
    fwrite($fh, time() . "\n");
    fwrite($fh, $json);
    fclose($fh);

    return $json;
}


        $NumberofUsersURL = 'https://'.$BaseURL.'.instructure.com/api/v1/accounts/1/analytics/current/statistics';
        $theNumberofStudents = json_decode(getJson($NumberofUsersURL))->students;
               
        $numberofpages = ceil($theNumberofStudents/$resultlimit)+5;
        echo "Building a report wth ".$theNumberofStudents." active users. This may take a while. You should probably go and make some coffee...";


//loop through pages of users
for ($i = 1; $i < $numberofpages; $i++)  {


//get every user in the account
        $Usersurl = 'https://'.$BaseURL.'.instructure.com/api/v1/accounts/1/users?per_page='.$resultlimit.'&page='.$i;
        $user_res_json = json_decode(getJson($Usersurl));
       // print_r($user_res_json);

//lets loop through for each user and get all the enrollment information we need
        foreach ($user_res_json as $unique_user) {
           
            $user_id = $unique_user->id; 
            // lets look at the enrollment API
            $enrollmenturl = 'https://'.$BaseURL.'.instructure.com/api/v1/users/'.$user_id.'/enrollments';
            $enrollment_res_json = json_decode(getJson($enrollmenturl));
            $enrollment_unique_array = array_unique($enrollment_res_json, SORT_REGULAR);

            //print_r($enrollment_res_json);
            $individualuserurl = 'https://'.$BaseURL.'.instructure.com/api/v1/users/'.$user_id.'/profile';
            $individualuserurl_res_json = json_decode(getJson($individualuserurl));


            //enrollment information
                foreach ($enrollment_unique_array as $enrollment_item) {
                      

                        $individualcourseurl = 'https://'.$BaseURL.'.instructure.com/api/v1/courses/'.$enrollment_item->course_id.'?include[]=term';    
                        $individualcourseurl_res_json = json_decode(getJson($individualcourseurl));
                        $individualsectionurl = 'https://'.$BaseURL.'.instructure.com/api/v1/sections/'.$enrollment_item->course_section_id;
                        $individualsectionurl_res_json = json_decode(getJson($individualsectionurl));


                            if ($enrollment_item->role == "StudentEnrollment") {

                                $EnrolmentFileline = array(
            
                                    //user_name
                                    $enrollment_item->user->sortable_name, 
                                    //User role
                                    $enrollment_item->role, 
                                    //time Zone
                                    $individualuserurl_res_json->time_zone, 
                                    //Latest Activity
                                    $enrollment_item->last_activity_at, 
                                    //User_ID
                                    $enrollment_item->user->id, 
                                    //Course name
                                    $individualcourseurl_res_json->name, 
                                    //Course ID
                                   // $enrollment_item->course_id, 
                                    //Section Name
                                    $individualsectionurl_res_json->name, 
                                    //Section ID
                                    $enrollment_item->course_section_id, 
                                    //Enrollment Start Date
                                    $enrollment_item->created_at, 
                                    //Enrollment End Date
                                    $enrollment_item->end_at, 
                                    //Current Score
                                    $enrollment_item->grades->current_score, 
                                    //Final Score
                                    $enrollment_item->grades->final_score, 
                                    //Total time in Course
                                    $enrollment_item->total_activity_time,
                                    //Enrollment State
                                    $enrollment_item->enrollment_state, 
                                    //Root account ID
                                    $individualcourseurl_res_json->account_id 
                                );

                                fputcsv($outputcompleted,$EnrolmentFileline);
                                echo ".";
                            }


                            //if its a teacher enrollment lets push to a different file
                            else if ($enrollment_item->role == "TeacherEnrollment") {

    
                                //lets build the teacher enrollment file
                                $EnrolmentFilelineTeacher = array(
                                //user_name
                                    $enrollment_item->user->sortable_name, 
                                    //User role
                                    $enrollment_item->role, 
                                    //time Zone
                                    $individualuserurl_res_json->time_zone, 
                                    //Latest Activity
                                    $enrollment_item->last_activity_at, 
                                    //User_ID
                                    $enrollment_item->user->id, 
                                    //Course ID
                                    $enrollment_item->course_id, 
                                    //Course name
                                    $individualcourseurl_res_json->name,
                                    //course Short Name
                                    $individualcourseurl_res_json->course_code,
                                    //Term ID and name
                                    $individualcourseurl_res_json->term->id,
                                    $individualcourseurl_res_json->term->name,
                                    $individualcourseurl_res_json->term->workflow_state,
                                    //is the course published?
                                    $individualcourseurl_res_json->workflow_state, 
                                    //Section Name
                                    $individualsectionurl_res_json->name, 
                                    //Section ID
                                    $enrollment_item->course_section_id, 
                                    //Enrollment Start Date
                                    $enrollment_item->created_at, 
                                    //Enrollment End Date
                                    $enrollment_item->end_at, 
                                    //Current Score
                                    //Total time in Course
                                    $enrollment_item->total_activity_time,
                                    //Enrollment State
                                    $enrollment_item->enrollment_state, 
                                    //Root account ID
                                    $individualcourseurl_res_json->account_id 
                                );

                                //push it to a csv we made earlier
                                fputcsv($outputcompletedteachers, $EnrolmentFilelineTeacher);
                                echo "+";
                            }

                            else {
                                // lets ignore any stupid ones
                                echo "x";

                            }

                 

                    


                    }



                    //if the enrollment is student then lets push to one file. 
                   

// this bracket closes the enrollments loop
}

//now the enrollments are sorted lets get that quiz information
}

fclose($outputcompleted); 
fclose($outputcompletedteachers); 
//fclose($outputcompletedquizzes); 
