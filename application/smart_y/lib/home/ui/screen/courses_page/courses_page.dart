import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:inject/inject.dart';
import 'package:smarty/home/bloc/courses_page/courses_page.bloc.dart';
import 'package:smarty/home/model/course/course_list_item.model.dart';
import 'package:smarty/home/ui/widget/app_drawer/app_drawer.dart';
import 'package:smarty/home/ui/widget/course_card/course_card.dart';
import 'package:smarty/home/ui/widget/image_icon/image_icon.dart';
import 'package:smarty/home/ui/widget/loading_indicator/loading_indicator.dart';
import 'package:smarty/home/ui/widget/smart_app_bar/smarty_app_bar.dart';
import 'package:smarty/utils/logger/logger.dart';

@provide
class CoursesPage extends StatefulWidget {
  final String tag = "CoursesPage";

  final CoursesPageBloc _coursesPageBloc;
  final Logger _logger;

  CoursesPage(this._coursesPageBloc, this._logger);

  @override
  _CoursesPageState createState() => _CoursesPageState();
}

class _CoursesPageState extends State<CoursesPage> {
  int currentState = CoursesPageBloc.STATUS_CODE_INIT;
  List<CourseModel> courses;

  @override
  Widget build(BuildContext context) {
    widget._coursesPageBloc.loginStateObservable.listen((stateChanged) {
      currentState = stateChanged.first;

      if (currentState == CoursesPageBloc.STATUS_CODE_FETCHING_DATA_SUCCESS) {
        this.courses = stateChanged.last;
      }

      if (this.mounted) {
        setState(() {
          //Your state change code goes here
        });
      }
    });

    if (currentState == CoursesPageBloc.STATUS_CODE_INIT) {
      widget._logger.info(widget.tag, "Courses List Page Started");
      widget._coursesPageBloc.getCourses();
    }

    if (currentState == CoursesPageBloc.STATUS_CODE_FETCHING_DATA) {
      widget._logger.info(widget.tag, "Fetching data from the server");
      return LoadingIndicatorWidget();
    }

    if (currentState == CoursesPageBloc.STATUS_CODE_FETCHING_DATA_SUCCESS) {
      widget._logger.info(widget.tag, "Fetching data SUCCESS");
      return getPageLayout();
    }

    if (currentState == CoursesPageBloc.STATUS_CODE_FETCHING_DATA_ERROR) {
      widget._logger.info(widget.tag, "Fetching data Error");
      return Scaffold(
          body: Center(
        child: Flex(
          direction: Axis.vertical,
          children: <Widget>[
            Text("Fetching data Error.."),
            RaisedButton(
              child: Text('Refresh'),
              onPressed: () {
                widget._coursesPageBloc.getCourses();
              },
            )
          ],
        ),
      ));
    }

    // Undefined State
    widget._logger.error(widget.tag, "Undefined State");
    return Scaffold(
      body: Center(
        child: Text("Undefined State?!!"),
      ),
    );
  }

  Widget getPageLayout() {
    return Scaffold(
      appBar: SmartyAppBarWidget(
        appBar: AppBar(),
        title: 'Courses',
      ),
      drawer: AppDrawerWidget(),
      body: Container(
        color: Color(0xffF4ECEC),
        child: Stack(
          children: <Widget>[
            ListView.builder(
                itemCount: courses.length,
                padding: EdgeInsetsDirectional.fromSTEB(15, 50, 15, 10),
                itemBuilder: (BuildContext context, int index) {
                  return CourseCardWidget(
                    image: 'assets/yoga.jpg',
                    price: 50,
                    chapters: 42,
                    name: courses[index].title,
                    description: courses[index].content,
                  );
                }),
            Positioned(
              left: 0.0,
              right: 0.0,
              top: 0.0,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      IconButton(
                        onPressed: () {},
                        icon: ImageAsIconWidget(
                          img: 'assets/filter_icon.png',
                          width: 20,
                          height: 10,
                        ),
                      ),
                      Text('Filter')
                    ],
                  ),
                  Row(
                    children: <Widget>[
                      IconButton(
                        onPressed: () {},
                        icon: ImageAsIconWidget(
                          img: 'assets/filter_icon.png',
                          width: 20,
                          height: 10,
                        ),
                      ),
                      Text('Sort')
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}